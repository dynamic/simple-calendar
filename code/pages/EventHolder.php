<?php

/**
 * Class EventHolder
 *
 * @property string $ICSFeed
 * @property int $EventsPerPage
 * @property string $RangeToShow
 */
class EventHolder extends Page
{
    /**
     * @var string
     */
    public static $item_class = 'EventPage';

    /**
     * @var array
     */
    private static $allowed_children = array('EventPage');

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
     * @var array
     */
    private static $db = array(
        'ICSFeed' => 'Varchar(255)',
        'EventsPerPage' => 'Int',
        //0 == All
        'RangeToShow' => 'Enum("Month,Year,All Upcoming","Month")',
        //TODO add day option, bug in getFeedEvents date logic
    );

    /**
     * @return FieldList
     */
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        $fields->addFieldToTab(
            'Root.Main',
            DropdownField::create(
                'RangeToShow',
                'Range to show',
                singleton('EventHolder')->dbObject('RangeToShow')->enumValues()
            ),
            'Content'
        );
        $fields->addFieldToTab(
            'Root.Main',
            NumericField::create(
                'EventsPerPage'
            )->setTitle('Events to show per page (0 shows all based on the "Rage to show")'),
            'Content'
        );

        return $fields;
    }

    /**
     * @param null $start_date
     * @param null $end_date
     * @return ArrayList
     */
    public function getFeedEvents($start_date = null, $end_date = null)
    {
        $start = sfDate::getInstance(strtotime('now'));
        $end_date = $this->buildEndDate($start);

        // single day views don't pass end dates
        if ($end_date) {
            $end = sfDate::getInstance($end_date);
        } else {
            $end = false;
        }

        $feedevents = new ArrayList();
        $feedreader = new ICSReader($this->ICSFeed);
        $events = $feedreader->getEvents();
        foreach ($events as $event) {
            // translate iCal schema into CalendarAnnouncement schema (datetime + title/content)
            $feedevent = new EventPage();
            $feedevent->Title = $event['SUMMARY'];
            if (isset($event['DESCRIPTION'])) {
                $feedevent->Content = $event['DESCRIPTION'];
            }

            $startdatetime = $this->iCalDateToDateTime($event['DTSTART']);
            $enddatetime = $this->iCalDateToDateTime($event['DTEND']);

            if (($end !== false) && (($startdatetime->get() < $start->get() && $enddatetime->get() < $start->get())
                    || $startdatetime->get() > $end->get() && $enddatetime->get() > $end->get())
            ) {
                // do nothing; dates outside range
            } else {
                if ($startdatetime->get() > $start->get()) {
                    $feedevent->Date = $startdatetime->format('Y-m-d');
                    $feedevent->Time = $startdatetime->format('H:i:s');

                    $feedevent->EndDate = $enddatetime->format('Y-m-d');
                    $feedevent->EndTime = $enddatetime->format('H:i:s');

                    $feedevents->push($feedevent);
                }
            }
        }

        return $feedevents;
    }

    /**
     * @param $date
     * @return mixed
     */
    public function iCalDateToDateTime($date)
    {
        date_default_timezone_set($this->stat('timezone'));
        $date = str_replace('T', '', $date);//remove T
        $date = str_replace('Z', '', $date);//remove Z
        $date = strtotime($date);
        $date = (!date('I')) ? $date : strtotime('- 1 hour', $date);
        $date = $date + date('Z');
        return sfDate::getInstance($date);
    }

    /**
     * @param null $start
     * @return bool|false|null|string
     */
    public function buildEndDate($start = null)
    {
        if ($start === null) {
            $start = sfDate::getInstance(strtotime('now'));
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
     * @return DataList
     */
    public static function getUpcomingEvents($filter = array())
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
     * @return DataList
     */
    public function getItemsShort()
    {
        return EventPage::get()
            ->limit(3)
            ->filter(array(
                'Date:LessThan:Not' => date('Y-m-d', strtotime('now')),
                'ParentID' => $this->ID,
            ))
            ->sort('Date', 'ASC');
    }
}

class EventHolder_Controller extends Page_Controller
{
    /**
     * @var array
     */
    private static $allowed_actions = array(
        'tag',
    );

    /**
     * @param array $filter
     * @return PaginatedList
     */
    public function Items($filter = array())
    {
        $filter['ParentID'] = $this->data()->ID;

        $items = $this->getUpcomingEvents($filter);

        $list = PaginatedList::create($items, $this->request);
        $list->setPageLength($this->data()->EventsPerPage);

        return $list;
    }

    /**
     * @return ViewableData_Customised|PaginatedList
     */
    public function tag()
    {
        $request = $this->request;
        $params = $request->allParams();

        if ($tag = Convert::raw2sql(urldecode($params['ID']))) {
            $filter = array('Tags.Title' => $tag);

            return $this->customise(array(
                'Message' => 'showing entries tagged "' . $tag . '"',
                'Items' => $this->Items($filter),
            ));
        }

        return $this->Items();
    }

    /**
     * @param array $filter
     * @return mixed
     */
    public function getUpcomingEvents($filter = array())
    {
        $filter['EndDate:GreaterThanOrEqual'] = date('Y-m-d', strtotime('now'));
        if ($this->data()->RangeToShow != 'All Upcoming') {
            $end_date = $this->data()->buildEndDate();
            $filter['Date:LessThanOrEqual'] = $end_date;
        }
        $items = $this->data()->getEvents($filter, 0);

        return $items->sort(array(
            'Date' => 'ASC',
            'Time' => 'ASC',
        ));
    }
}
