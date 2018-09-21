<?php

namespace Dynamic\Calendar\Pages;

use SilverStripe\ORM\ArrayList;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\NumericField;

/**
 * Class EventHolder
 * @package Dynamic\Calendar
 */
class EventHolder extends \Page
{
    /**
     * @var array
     */
    private static $allowed_children = [
        EventPage::class,
    ];

    /**
     * @var string
     */
    private static $singular_name = 'Calendar';

    /**
     * @var string
     */
    private static $plural_name = 'Calendars';

    /**
     * @var string
     */
    private static $description = 'displays a list of upcoming events';

    /**
     * @var string
     */
    private static $timezone = 'America/Chicago';

    /**
     * @var string
     */
    private static $table_name = 'EventHolder';

    /**
     * @var array
     */
    private static $db = [
        'ICSFeed' => 'Varchar(255)',
        'EventsPerPage' => 'Int',
    ];

    /**
     * @return FieldList
     */
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        $fields->addFieldToTab(
            'Root.Main',
            NumericField::create(
                'EventsPerPage'
            )->setTitle('Events to show per page (0 shows all based on the "Range to show")'),
            'Content'
        );

        return $fields;
    }

    /**
     * @param null $start
     * @return bool|false|null|string
     */
    public function buildEndDate($start = null)
    {
        if ($start === null) {
            $start = \sfDate::getInstance(strtotime('now'));
        }

        switch ($this->RangeToShow) {
            case 'Day':
                $end_date = $start;
                break;
            case 'Year':
                $end_date = date('Y-m-d', strtotime(date('Y-m-d', time()) . ' + 365 day'));
                break;
            case 'All Upcoming':
                $end_date = false;
                break;
            default:
                $end_date = date('Y-m-d', strtotime(date('Y-m-d', time()) . ' + 1 month'));
                break;
        }

        return $end_date;
    }

    /**
     * @param array $filter
     * @return \SilverStripe\ORM\DataList
     */
    public static function getUpcomingEvents($filter = [])
    {
        $filter['Date:GreaterThanOrEqual'] = date('Y-m-d', strtotime('now'));
        $events = EventPage::get()
            ->filter($filter)
            ->sort('Date', 'ASC');

        return $events;
    }

    /**
     * @param null $filter
     * @return ArrayList
     */
    public function getEvents($filter = null)
    {
        $eventList = ArrayList::create();
        $events = static::getUpcomingEvents($filter);
        $eventList->merge($events);
        if ($this->ICSFeed) {
            $icsEvents = $this->getFeedEvents();
            $eventList->merge($icsEvents);
        }

        return $eventList;
    }

    /**
     * @return \SilverStripe\ORM\DataList
     */
    public function getItemsShort()
    {
        return EventPage::get()
            ->limit(3)
            ->filter([
                'Date:LessThan:Not' => date('Y-m-d', strtotime('now')),
                'ParentID' => $this->ID,
            ])
            ->sort('Date', 'ASC');
    }
}
