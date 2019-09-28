<?php
function elapsed_secs_to_h($secs)
{
        $units = array(
				"year"   => 52*7*24*3600,
				"month"  =>   30*24*3600,
                "week"   =>    7*24*3600,
                "day"    =>      24*3600,
                "hour"   =>         3600,
                "minute" =>           60,
                "second" =>            1,
        );

	// specifically handle zero
        if ( $secs == 0 ) return "0 seconds";

        $s = "";

        foreach ( $units as $name => $divisor ) {
                if ( $quot = intval($secs / $divisor) ) {
                        $s .= "$quot $name";
                        $s .= (abs($quot) > 1 ? "s" : "");
						return $s;
                        $secs -= $quot * $divisor;
                }
        }

        return substr($s, 0, -2);
}