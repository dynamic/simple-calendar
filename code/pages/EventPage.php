<?php

/**
 * Class EventPage
 *
 * @property Date $Date
 * @property Date $EndDate
 * @property Time $Time
 * @property Time $EndTime
 * @property bool $HideLink
 */
class EventPage extends Page
{
    /**
     * @var string
     */
    private static $singular_name = 'Event';

    /**
     * @var string
     */
    private static $plural_name = 'Events';

    /**
     * @var string
     */
    private static $description = 'Event detail page';

    /**
     * @var array
     */
    private static $db = array(
        'Date' => 'Date',
        'EndDate' => 'Date',
        'Time' => 'Time',
        'EndTime' => 'Time',
        'HideLink' => 'Boolean',
    );

    /**
     * @var array
     */
    private static $defaults = array(
        'ShowInMenus' => 0
    );

    /**
     * @return FieldList
     */
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        $fields->addFieldToTab('Root.EventInformation', DateField::create('Date')->setTitle('Event Start Date'));
        $fields->addFieldToTab('Root.EventInformation', DateField::create('EndDate')->setTitle('Event End Date'));
        $fields->addFieldToTab('Root.EventInformation', TimeField::create('Time')->setTitle('Event Time'));
        $fields->addFieldToTab('Root.EventInformation', TimeField::create('EndTime')->setTitle('Event End Time'));

        $fields->insertAfter(CheckboxField::create('HideLink', 'Hide Link to Event'), 'URLSegment');

        return $fields;
    }

    /**
     * @return ValidationResult
     */
    public function validate()
    {
        $result = parent::validate();

        if ($this->EndTime && ($this->Time > $this->EndTime)) {
            $result->error('End Time must be later than the Start Time');
        }

        if ($this->EndDate && ($this->Date > $this->EndDate)) {
            $result->error('End Date must be equal to the Start Date or in the future');
        }

        return $result;
    }

    /**
     *
     */
    public function onBeforeWrite()
    {
        parent::onBeforeWrite();
        if (!$this->EndDate) {
            $this->EndDate = $this->Date;
        }
    }
}

/**
 * Class EventPage_Controller
 */
class EventPage_Controller extends Page_Controller
{
}
