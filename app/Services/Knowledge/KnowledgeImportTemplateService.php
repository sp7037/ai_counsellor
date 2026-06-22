<?php

namespace App\Services\Knowledge;

use App\Enums\Knowledge\KnowledgeImportType;
use Illuminate\Validation\ValidationException;

class KnowledgeImportTemplateService
{
    /**
     * @return array<string, string>
     */
    public function filenames(): array
    {
        return [
            KnowledgeImportType::Faq->value => 'faq-import-template.csv',
            KnowledgeImportType::CourseInfo->value => 'course-info-import-template.csv',
            KnowledgeImportType::Fee->value => 'fee-import-template.csv',
            KnowledgeImportType::Eligibility->value => 'eligibility-import-template.csv',
        ];
    }

    public function content(string $type): string
    {
        $importType = KnowledgeImportType::tryFrom($type);

        if ($importType === null) {
            throw ValidationException::withMessages(['type' => 'Unknown import template type.']);
        }

        return match ($importType) {
            KnowledgeImportType::Faq => $this->faqTemplate(),
            KnowledgeImportType::CourseInfo => $this->courseTemplate(),
            KnowledgeImportType::Fee => $this->feeTemplate(),
            KnowledgeImportType::Eligibility => $this->eligibilityTemplate(),
        };
    }

    private function faqTemplate(): string
    {
        return implode("\n", [
            'question,answer,category,tags,status',
            '"What documents are required for MBBS abroad?","Passport, NEET scorecard, academic transcripts, and passport photos.","Admissions","mbbs,documents",draft',
        ])."\n";
    }

    private function courseTemplate(): string
    {
        return implode("\n", [
            'title,body,category,tags,status',
            '"MBBS in Georgia","Six-year English-medium MBBS program with WHO/NMC listed universities.","Courses","mbbs,georgia",draft',
        ])."\n";
    }

    private function feeTemplate(): string
    {
        return implode("\n", [
            'label,fee_type,amount_minor,currency,notes,status',
            '"MBBS Georgia tuition","exact","3500000","INR","Approximate first-year tuition in paise/minor units",draft',
        ])."\n";
    }

    private function eligibilityTemplate(): string
    {
        return implode("\n", [
            'title,required_criteria,preferred_criteria,priority,status',
            '"MBBS abroad baseline","NEET qualification and Class 12 PCB minimum 50%","Higher PCB marks and English proficiency",100,draft',
        ])."\n";
    }
}
