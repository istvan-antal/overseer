<?php

namespace Overseer;

use DateTime;

class TimeHelper {

    protected $formatter;

    public function __construct() {
    }

    /**
     * Returns a single number of years, months, days, hours, minutes or
     * seconds between the specified date times.
     *
     * @param  mixed $since The datetime for which the diff will be calculated
     * @param  mixed $since The datetime from which the diff will be calculated
     *
     * @return string
     */
    public function diff($from, $to = null) {
        $from = $this->getDatetimeObject($from);
        $to = $this->getDatetimeObject($to);

        static $units = array(
            'y' => 'year',
            'm' => 'month',
            'd' => 'day',
            'h' => 'hour',
            'i' => 'minute',
            's' => 'second'
        );

        $diff = $to->diff($from);

        foreach ($units as $attribute => $unit) {
            $count = $diff->$attribute;
            if (0 !== $count) {
                return $count . ' ' . $unit.($count > 0 ? 's' : '');
            }
        }

        return 'now';
    }

    /**
     * Returns a DateTime instance for the given datetime
     *
     * @param  mixed $datetime
     *
     * @return DateTime
     */
    public function getDatetimeObject($datetime = null) {
        if ($datetime instanceof DateTime) {
            return $datetime;
        }

        if (is_integer($datetime)) {
            $datetime = date('Y-m-d H:i:s', $datetime);
        }

        return new DateTime($datetime);
    }

    public function getName() {
        return 'time';
    }

}
