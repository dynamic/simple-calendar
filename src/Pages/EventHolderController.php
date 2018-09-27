<?php

namespace Dynamic\Calendar\Pages;

/**
 * Class EventHolderController
 * @package Dynamic\Calendar\Pages
 */
class EventHolderController extends \PageController
{
    /**
     * @var
     */
    private $events;

    /**
     * @return mixed
     */
    public function getEvents()
    {
        if (!$this->events) {
            $this->setEvents();
        }

        return $this->events;
    }

    /**
     * @return $this
     */
    public function setEvents()
    {
        $request = $this->getRequest();

        $startDate = ($request->getVar('startday'))
            ? $request->getVar('startday')
            : date('Y-m-d');

        $endDate = ($request->getVar('endday'))
            ? $request->getVar('endday')
            : false;

        $events = EventPage::get()
            ->filter('Date:GreaterThanOrEqual', $startDate);

        if ($endDate !== false) {
            $events = $events->filter('EndDate:LessThanOrEqual', $endDate);
        }

        $this->extend('updateSetEvents', $events);

        $this->events = $events;

        return $this;
    }
}
