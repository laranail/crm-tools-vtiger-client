<?php declare(strict_types=1);

namespace Simtabi\Pheg\Core\Support\Traits;

trait DataHelpersTrait
{

    private string $dataKey = 'supports';

    public function getUserGroups(?string $default = null)
    {
        return $this->setFileName($this->dataKey)->setKey('user_groups')->setDefault($default)->getData();
    }

    public function getAccessGroups(?string $default = null)
    {
        return $this->setFileName($this->dataKey)->setKey('access_groups')->setDefault($default)->getData();
    }

    public function getSecurityStatuses(?string $default = null)
    {
        return $this->setFileName($this->dataKey)->setKey('security_statuses')->setDefault($default)->getData();
    }

    public function getAgeLimits(?string $default = null)
    {
        return $this->setFileName($this->dataKey)->setKey('age_limits')->setDefault($default)->getData();
    }

    public function getTriggerFrequencies(?string $default = null)
    {
        return $this->setFileName($this->dataKey)->setKey('trigger_frequencies')->setDefault($default)->getData();
    }

    public function getTimeOptions(?string $default = null)
    {
        return $this->setFileName($this->dataKey)->setKey('time_options')->setDefault($default)->getData();
    }

    public function getCalendarOptions(?string $default = null)
    {
        return $this->setFileName($this->dataKey)->setKey('calendar_options')->setDefault($default)->getData();
    }

    public function getDatetimeFormats(?string $default = null)
    {
        return $this->setFileName($this->dataKey)->setKey('datetime_formats')->setDefault($default)->getData();
    }

    public function getLanguageOptions(?string $default = null)
    {
        return $this->setFileName($this->dataKey)->setKey('language_options')->setDefault($default)->getData();
    }

    public function getLinkTargeOptions(?string $default = null)
    {
        return $this->setFileName($this->dataKey)->setKey('link_target_options')->setDefault($default)->getData();
    }

    public function getPriorityTypes(?string $default = null)
    {
        return $this->setFileName($this->dataKey)->setKey('priority_types')->setDefault($default)->getData();
    }

    public function getContentStatuses(?string $default = null)
    {
        return $this->setFileName($this->dataKey)->setKey('content_statuses')->setDefault($default)->getData();
    }

    public function getProgressStatusTypes(?string $default = null)
    {
        return $this->setFileName($this->dataKey)->setKey('progress_status_types')->setDefault($default)->getData();
    }

    public function getAspectRatios(?string $default = null)
    {
        return $this->setFileName($this->dataKey)->setKey('aspect_ratios')->setDefault($default)->getData();
    }

    public function getAvatarTypes(?string $default = null)
    {
        return $this->setFileName($this->dataKey)->setKey('avatar_types')->setDefault($default)->getData();
    }

    public function getUploadMethods(?string $default = null)
    {
        return $this->setFileName($this->dataKey)->setKey('upload_methods')->setDefault($default)->getData();
    }

    public function getGendersTypes(?string $default = null)
    {
        return $this->setFileName($this->dataKey)->setKey('genders')->setDefault($default)->getData();
    }

    public function getAvailabilityTypes(?string $default = null)
    {
        return $this->setFileName($this->dataKey)->setKey('availability_types')->setDefault($default)->getData();
    }

    public function getEmploymentStatusTypes(?string $default = null)
    {
        return $this->setFileName($this->dataKey)->setKey('employment_status_types')->setDefault($default)->getData();
    }

    public function getSocialMediaProviders(?string $default = null)
    {
        return $this->setFileName($this->dataKey)->setKey('socialmedia_providers')->setDefault($default)->getData();
    }

    public function getSalutations(?string $default = null)
    {
        return $this->setFileName($this->dataKey)->setKey('salutations')->setDefault($default)->getData();
    }

    public function getMediaTypes(?string $default = null)
    {
        return $this->setFileName($this->dataKey)->setKey('media_types')->setDefault($default)->getData();
    }

    public function getArticleFormatsTypes(?string $default = null)
    {
        return $this->setFileName($this->dataKey)->setKey('article_formats')->setDefault($default)->getData();
    }

    public function getArticleTypes(?string $default = null)
    {
        return $this->setFileName($this->dataKey)->setKey('article_types')->setDefault($default)->getData();
    }

    public function getMailingProtocols(?string $default = null)
    {
        return $this->setFileName($this->dataKey)->setKey('mailing_protocols')->setDefault($default)->getData();
    }

    public function getAuthOptions(?string $default = null)
    {
        return $this->setFileName($this->dataKey)->setKey('auth_options')->setDefault($default)->getData();
    }

    public function getProfessionTypes(?string $default = null)
    {
        return $this->setFileName($this->dataKey)->setKey('profession_types')->setDefault($default)->getData();
    }

    public function getCompanyRegistrationTypes(?string $default = null)
    {
        return $this->setFileName($this->dataKey)->setKey('company_registration_types')->setDefault($default)->getData();
    }

    public function getOccupationTypes(?string $default = null)
    {
        return $this->setFileName($this->dataKey)->setKey('occupation_types')->setDefault($default)->getData();
    }

    public function getIndustryTypes(?string $default = null)
    {
        return $this->setFileName($this->dataKey)->setKey('industry_types')->setDefault($default)->getData();
    }

    public function getCareerTypes(?string $default = null)
    {
        return $this->setFileName($this->dataKey)->setKey('career_types')->setDefault($default)->getData();
    }

    public function getCopyrightTexts(?string $default = null)
    {
        return $this->setFileName($this->dataKey)->setKey('copyright_texts')->setDefault($default)->getData();
    }

    public function getMenuLocations(?string $default = null)
    {
        return $this->setFileName($this->dataKey)->setKey('menu_locations')->setDefault($default)->getData();
    }

    public function getLinkTypes(?string $default = null)
    {
        return $this->setFileName($this->dataKey)->setKey('link_types')->setDefault($default)->getData();
    }

    public function getAnchorTypes(?string $default = null)
    {
        return $this->setFileName($this->dataKey)->setKey('anchor_types')->setDefault($default)->getData();
    }

    public function getLinkTo(?string $default = null)
    {
        return $this->setFileName($this->dataKey)->setKey('link_to')->setDefault($default)->getData();
    }

    public function getSearchOptions(?string $default = null)
    {
        return $this->setFileName($this->dataKey)->setKey('search_options')->setDefault($default)->getData();
    }

    public function getAddressGroups(?string $default = null)
    {
        return $this->setFileName($this->dataKey)->setKey('address_groups')->setDefault($default)->getData();
    }

    public function getHelpdeskOptions(?string $default = null)
    {
        return $this->setFileName($this->dataKey)->setKey('helpdesk_options')->setDefault($default)->getData();
    }

    public function getNotificationOptions(?string $default = null)
    {
        return $this->setFileName($this->dataKey)->setKey('notification_options')->setDefault($default)->getData();
    }

}
