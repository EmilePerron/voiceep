<?php

namespace App\Helper;

class Comparison {
    public static function getLongestCommonSubstring(Array $strings) {
        $strings = array_map('trim', $strings);
        $sort_by_strlen = create_function('$a, $b', 'if (strlen($a) == strlen($b)) { return strcmp($a, $b); } return (strlen($a) < strlen($b)) ? -1 : 1;');
        usort($strings, $sort_by_strlen);
        $longest_common_substring = array();
        $shortest_string = str_split(array_shift($strings));

        while (sizeof($shortest_string)) {
            array_unshift($longest_common_substring, '');
            foreach ($shortest_string as $char) {
                foreach ($strings as $string) {
                    if (!($longest_common_substring[0] . $char) || !strstr($string, $longest_common_substring[0] . $char)) {
                        // No match
                        break 2;
                    }
                }
                $longest_common_substring[0] .= $char;
            }
            array_shift($shortest_string);
        }

        // If we made it here then we've run through everything
        usort($longest_common_substring, $sort_by_strlen);
        return array_pop($longest_common_substring);
    }
}
