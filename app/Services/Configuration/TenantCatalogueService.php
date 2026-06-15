<?php

namespace App\Services\Configuration;

use App\Enums\Audit\AuditAction;
use App\Enums\Configuration\CatalogueStatus;
use App\Models\Course;
use App\Models\Institution;
use App\Models\Location;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TenantCatalogueService
{
    public function __construct(
        private readonly ConfigurationValidator $validator,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function createService(Tenant $tenant, array $data, User $actor): Service
    {
        return $this->createItem($tenant, Service::class, AuditAction::ServiceCreated, $data, $actor);
    }

    public function updateService(Service $service, array $data, User $actor): Service
    {
        return $this->updateItem($service, AuditAction::ServiceUpdated, $data, $actor);
    }

    public function setServiceStatus(Service $service, CatalogueStatus $status, User $actor): Service
    {
        return $this->setStatus($service, $status, AuditAction::ServiceActivated, AuditAction::ServiceDeactivated, $actor);
    }

    public function removeService(Service $service, User $actor): void
    {
        $this->removeItem($service, AuditAction::ServiceRemoved, $actor);
    }

    public function createCourse(Tenant $tenant, array $data, User $actor): Course
    {
        return $this->createItem($tenant, Course::class, AuditAction::CourseCreated, $data, $actor);
    }

    public function updateCourse(Course $course, array $data, User $actor): Course
    {
        return $this->updateItem($course, AuditAction::CourseUpdated, $data, $actor);
    }

    public function setCourseStatus(Course $course, CatalogueStatus $status, User $actor): Course
    {
        return $this->setStatus($course, $status, AuditAction::CourseActivated, AuditAction::CourseDeactivated, $actor);
    }

    public function removeCourse(Course $course, User $actor): void
    {
        $this->removeItem($course, AuditAction::CourseRemoved, $actor);
    }

    public function createInstitution(Tenant $tenant, array $data, User $actor): Institution
    {
        return $this->createItem($tenant, Institution::class, AuditAction::InstitutionCreated, $data, $actor);
    }

    public function updateInstitution(Institution $institution, array $data, User $actor): Institution
    {
        return $this->updateItem($institution, AuditAction::InstitutionUpdated, $data, $actor);
    }

    public function setInstitutionStatus(Institution $institution, CatalogueStatus $status, User $actor): Institution
    {
        return $this->setStatus($institution, $status, AuditAction::InstitutionActivated, AuditAction::InstitutionDeactivated, $actor);
    }

    public function removeInstitution(Institution $institution, User $actor): void
    {
        $this->removeItem($institution, AuditAction::InstitutionRemoved, $actor);
    }

    public function createLocation(Tenant $tenant, array $data, User $actor): Location
    {
        return $this->createItem($tenant, Location::class, AuditAction::LocationCreated, $data, $actor);
    }

    public function updateLocation(Location $location, array $data, User $actor): Location
    {
        return $this->updateItem($location, AuditAction::LocationUpdated, $data, $actor);
    }

    public function setLocationStatus(Location $location, CatalogueStatus $status, User $actor): Location
    {
        return $this->setStatus($location, $status, AuditAction::LocationActivated, AuditAction::LocationDeactivated, $actor);
    }

    public function removeLocation(Location $location, User $actor): void
    {
        $this->removeItem($location, AuditAction::LocationRemoved, $actor);
    }

    /** @param  class-string<Model>  $modelClass */
    private function createItem(Tenant $tenant, string $modelClass, AuditAction $action, array $data, User $actor): Model
    {
        return DB::transaction(function () use ($tenant, $modelClass, $action, $data, $actor): Model {
            $this->assertUnderLimit($tenant, $modelClass);

            $payload = $this->normalizePayload($data);
            $payload['created_by'] = $actor->id;
            $payload['status'] = CatalogueStatus::Active->value;

            /** @var Model $item */
            $item = $modelClass::query()->create($payload);

            $this->auditLogger->log($action, $item, $tenant->id, ['name' => $item->getAttribute('name')], $actor);

            return $item;
        });
    }

    private function updateItem(Model $item, AuditAction $action, array $data, User $actor): Model
    {
        return DB::transaction(function () use ($item, $action, $data, $actor): Model {
            $before = ['name' => $item->getAttribute('name'), 'status' => $item->getAttribute('status')?->value ?? $item->getAttribute('status')];
            $item->update($this->normalizePayload($data, $item));
            $after = ['name' => $item->fresh()->name, 'status' => $item->fresh()->status?->value ?? $item->fresh()->status];

            $this->auditLogger->log($action, $item, (int) $item->getAttribute('tenant_id'), compact('before', 'after'), $actor);

            return $item->fresh();
        });
    }

    private function setStatus(Model $item, CatalogueStatus $status, AuditAction $activate, AuditAction $deactivate, User $actor): Model
    {
        return DB::transaction(function () use ($item, $status, $activate, $deactivate, $actor): Model {
            $before = $item->getAttribute('status')?->value ?? $item->getAttribute('status');
            $item->update(['status' => $status->value]);
            $action = $status === CatalogueStatus::Active ? $activate : $deactivate;

            $this->auditLogger->log(
                $action,
                $item,
                (int) $item->getAttribute('tenant_id'),
                ['before' => ['status' => $before], 'after' => ['status' => $status->value]],
                $actor,
            );

            return $item->fresh();
        });
    }

    private function removeItem(Model $item, AuditAction $action, User $actor): void
    {
        DB::transaction(function () use ($item, $action, $actor): void {
            $snapshot = ['name' => $item->getAttribute('name'), 'uuid' => $item->getAttribute('uuid')];
            $tenantId = (int) $item->getAttribute('tenant_id');
            $item->delete();
            $this->auditLogger->log($action, null, $tenantId, ['before' => $snapshot], $actor);
        });
    }

    /** @param  class-string<Model>  $modelClass */
    private function assertUnderLimit(Tenant $tenant, string $modelClass): void
    {
        $count = $modelClass::query()->where('tenant_id', $tenant->id)->count();
        if ($count >= config('configuration.max_catalogue_items', 50)) {
            throw ValidationException::withMessages([
                'name' => 'Maximum number of items reached for this tenant.',
            ]);
        }
    }

    private function normalizePayload(array $data, ?Model $existing = null): array
    {
        $payload = [];

        if (array_key_exists('name', $data)) {
            $payload['name'] = $this->validator->sanitizePlainText((string) $data['name'], 160);
        }

        if (array_key_exists('description', $data)) {
            $payload['description'] = $this->validator->sanitizePlainText($data['description'], config('configuration.max_description_length', 2000));
        }

        foreach (['duration', 'city', 'state', 'country', 'address_line', 'pin_code', 'phone'] as $field) {
            if (array_key_exists($field, $data)) {
                $max = $field === 'address_line' ? 255 : 120;
                $payload[$field] = $this->validator->sanitizePlainText($data[$field], $max);
            }
        }

        if (array_key_exists('study_mode', $data) && $data['study_mode'] !== null && $data['study_mode'] !== '') {
            $payload['study_mode'] = $data['study_mode'];
        }

        if (array_key_exists('sort_order', $data)) {
            $payload['sort_order'] = max(0, min(999, (int) $data['sort_order']));
        }

        if ($existing !== null && isset($payload['name']) && $payload['name'] !== $existing->getAttribute('name')) {
            $payload['slug'] = $existing::generateUniqueSlug(
                $payload['name'],
                (int) $existing->getAttribute('tenant_id'),
                (int) $existing->getKey(),
            );
        }

        return $payload;
    }
}
