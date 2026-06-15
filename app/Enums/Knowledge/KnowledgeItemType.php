<?php

namespace App\Enums\Knowledge;

enum KnowledgeItemType: string
{
    case Faq = 'faq';
    case Policy = 'policy';
    case Article = 'article';
    case ServiceInfo = 'service_info';
    case CourseInfo = 'course_info';
    case InstitutionInfo = 'institution_info';
    case LocationInfo = 'location_info';
    case DocumentChecklist = 'document_checklist';
    case WorkflowInstruction = 'workflow_instruction';

    public function label(): string
    {
        return match ($this) {
            self::Faq => 'FAQ',
            self::Policy => 'Policy',
            self::Article => 'Article',
            self::ServiceInfo => 'Service information',
            self::CourseInfo => 'Course information',
            self::InstitutionInfo => 'Institution information',
            self::LocationInfo => 'Location information',
            self::DocumentChecklist => 'Document checklist',
            self::WorkflowInstruction => 'Workflow instruction',
        };
    }
}
