<?php
/**
 * RGB, HSL converters and more.
 */

namespace TerminalHue\Helpers;

class Converter {
    /**
     * Convert hex to Phillips Hue.
     * @param $color
     * @return bool
     */
    public static function hex2hue($color) {
        // Strip out the leading hashtag if there
        if(substr($color, 0, 1) == "#") $color = substr($color, 1);
        // If we don't now have 6 digits, we have a problem
        if(strlen($color) != 6) return false;
        // Get our red, blue, and green values in fractional form
        $red = ((float) hexdec(substr($color, 0, 2)))/255;
        $blue = ((float) hexdec(substr($color, 4, 2)))/255;
        $green = ((float) hexdec(substr($color, 2, 2)))/255;
        // we need these later
        $highest = max($red, $green, $blue);
        $diff = $highest - min($red, $green, $blue);
        // We want the light to be as bright as our brightest individual color.
        $brightness = $highest;
        // Calculate our saturation as well as values used in the hue formula
        if($diff == 0) {
            $base = 0;
            $delta = 0;
            $saturation = 0;
        } else {
            $saturation = $diff / $highest;
            $base = 0;
            $delta = 0;
            switch($highest) {
                case $red:
                    $base = 0;
                    $delta = ($green-$blue)/($diff*2);
                    break;
                case $green:
                    $base = 25500; // Pure green, per the documentation
                    $delta = ($blue-$red)/($diff*2);
                    break;
                case $blue:
                    $base = 46920; // Pure blue, per the documentation
                    $delta = ($red-$green)/($diff*2);
                    break;
            }
        }
        // Correction for red-dominant purples to a positive value above blue instead of a negative value below red
        if($delta < 0) {
            $base = 46920;
            $delta = (1 + $delta);
        }
        // Determine the conversion value for our delta value
        if($base < 2) $scaling = 25500; // red to green occupies 38.910505836% of Hue's color space
        elseif($base < 4) $scaling = 21420; // green to blue occupies occupy 32.684824902% of Hue's color space
        else $scaling = 18615; // blue to red occupies 28.40466926% of Hue's color space
        // Determine our appropriately-scaled hue value
        $return['hue'] = (int) ($base + ($delta * $scaling));
        // Scale up our brightness and saturation to the right units
        $return['saturation'] = (int) ($saturation * 255);
        $return['brightness'] = (int) ($brightness * 255);
        return $return;
    }

    /**
     * Convert Hex to XY.
     * Uses methods from http://www.developers.meethue.com/documentation/color-conversions-rgb-xy
     * Does not handle gamut adjustments.
     * @param $hex
     * @return array
     */
    public static function hexToXY($hex)
    {
        /**
         * Get RGB value for hex.
         */
        $rgb = self::hex2rgb($hex);

        /**
         * Convert RGB values to be fractions of 1.
         */
        array_walk($rgb, function(&$value) {
            $value = $value / 255;
        });

        /**
         * Apply gamma correction to RGB values.
         */
        array_walk($rgb, function(&$value) {
            $value = $value > 0.04045 ? pow(($value + 0.055) / (1.0 + 0.055), 2.4) : ($value / 12.92);
        });

        /**
         * Calculate XYZ values using the Wide RGB D65 conversion formula.
         */
        $wideX = $rgb[0] * 0.664511 + $rgb[1] * 0.154324 + $rgb[2] * 0.162028;
        $wideY = $rgb[0] * 0.283881 + $rgb[1] * 0.668433 + $rgb[2] * 0.047685;
        $wideZ = $rgb[0] * 0.000088 + $rgb[1] * 0.072310 + $rgb[2] * 0.986039;

        var_dump($rgb);

        /**
         * Calculate XY values from the XYZ values.
         */
        $x = $wideX / ($wideX + $wideY + $wideZ);
        $y = $wideY / ($wideX + $wideY + $wideZ);

        echo $x . ' ' . $y;
        return [$x, $y];
    }

    /**
     * Validates hex color code and returns proper value
     * Input: String - Format #ffffff, #fff, ffffff or fff
     * Output: hex value - 3 byte (000000 if input is invalid)
     * @param $hex
     * @return string
     */
    public static function validate_hex($hex) {
        // Complete patterns like #ffffff or #fff
        if(preg_match("/^#([0-9a-fA-F]{6})$/", $hex) || preg_match("/^#([0-9a-fA-F]{3})$/", $hex)) {
            // Remove #
            $hex = substr($hex, 1);
        }

        // Complete patterns without # like ffffff or 000000
        if(preg_match("/^([0-9a-fA-F]{6})$/", $hex)) {
            return $hex;
        }

        // Short patterns without # like fff or 000
        if(preg_match("/^([0-9a-f]{3})$/", $hex)) {
            // Spread to 6 digits
            return substr($hex, 0, 1) . substr($hex, 0, 1) . substr($hex, 1, 1) . substr($hex, 1, 1) . substr($hex, 2, 1) . substr($hex, 2, 1);
        }

        // If input value is invalid return black
        return "000000";
    }

    /**
     * Converts hex color code to RGB color
     * Input: String - Format #ffffff, #fff, ffffff or fff
     * Output: Array(Hue, Saturation, Lightness) - Values from 0 to 1
     * @param $hex
     * @return array
     */
    public static function hex2hsl($hex) {
        //Validate Hex Input
        $hex = self::validate_hex($hex);

        // Split input by color
        $hex = str_split($hex, 2);
        // Convert color values to value between 0 and 1
        $r = (hexdec($hex[0])) / 255;
        $g = (hexdec($hex[1])) / 255;
        $b = (hexdec($hex[2])) / 255;

        return self::rgb2hsl(array($r,$g,$b));
    }

    /**
     * Converts RGB color to HSL color
     * Check http://en.wikipedia.org/wiki/HSL_and_HSV#Hue_and_chroma for details
     * Input: Array(Red, Green, Blue) - Values from 0 to 1
     * Output: Array(Hue, Saturation, Lightness) - Values from 0 to 1
     * @param $rgb
     * @return array
     */
    public static function rgb2hsl($rgb) {
        // Fill variables $r, $g, $b by array given.
        list($r, $g, $b) = $rgb;

        // Determine lowest & highest value and chroma
        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $chroma = $max - $min;

        // Calculate Luminosity
        $l = ($max + $min) / 2;

        // If chroma is 0, the given color is grey
        // therefore hue and saturation are set to 0
        if ($chroma == 0)
        {
            $h = 0;
            $s = 0;
        }

        // Else calculate hue and saturation.
        // Check http://en.wikipedia.org/wiki/HSL_and_HSV for details
        else
        {
            switch($max) {
                case $r:
                    $h_ = fmod((($g - $b) / $chroma), 6);
                    if($h_ < 0) $h_ = (6 - fmod(abs($h_), 6)); // Bugfix: fmod() returns wrong values for negative numbers
                    break;

                case $g:
                    $h_ = ($b - $r) / $chroma + 2;
                    break;

                case $b:
                    $h_ = ($r - $g) / $chroma + 4;
                    break;
                default:
                    break;
            }

            $h = $h_ / 6;
            $s = 1 - abs(2 * $l - 1);
        }

        // Return HSL Color as array
        return array($h, $s, $l);
    }

    /**
     * Converts HSL color to RGB color
     * Input: Array(Hue, Saturation, Lightness) - Values from 0 to 1
     * Output: Array(Red, Green, Blue) - Values from 0 to 1
     * @param $hsl
     * @return array
     */
    public static function hsl2rgb($hsl) {
        // Fill variables $h, $s, $l by array given.
        list($h, $s, $l) = $hsl;

        // If saturation is 0, the given color is grey and only
        // lightness is relevant.
        if ($s == 0 ) {
            $rgb = array($l, $l, $l);
        }

        // Else calculate r, g, b according to hue.
        // Check http://en.wikipedia.org/wiki/HSL_and_HSV#From_HSL for details
        else
        {
            $chroma = (1 - abs(2*$l - 1)) * $s;
            $h_     = $h * 6;
            $x         = $chroma * (1 - abs((fmod($h_,2)) - 1)); // Note: fmod because % (modulo) returns int value!!
            $m = $l - round($chroma/2, 10); // Bugfix for strange float behaviour (e.g. $l=0.17 and $s=1)

            if($h_ >= 0 && $h_ < 1) $rgb = array(($chroma + $m), ($x + $m), $m);
            else if($h_ >= 1 && $h_ < 2) $rgb = array(($x + $m), ($chroma + $m), $m);
            else if($h_ >= 2 && $h_ < 3) $rgb = array($m, ($chroma + $m), ($x + $m));
            else if($h_ >= 3 && $h_ < 4) $rgb = array($m, ($x + $m), ($chroma + $m));
            else if($h_ >= 4 && $h_ < 5) $rgb = array(($x + $m), $m, ($chroma + $m));
            else if($h_ >= 5 && $h_ < 6) $rgb = array(($chroma + $m), $m, ($x + $m));
        }

        return $rgb;
    }

    /**
     * Converts RGB color to hex code
     * Input: Array(Red, Green, Blue)
     * Output: String hex value (#000000 - #ffffff)
     * @param $rgb
     * @return string
     */
    public static function rgb2hex($rgb) {
        list($r,$g,$b) = $rgb;
        $r = round(255 * $r);
        $g = round(255 * $g);
        $b = round(255 * $b);
        return "#".sprintf("%02X",$r).sprintf("%02X",$g).sprintf("%02X",$b);
    }

    /**
     * Converts Hex to RGB.
     * @param $hex
     * @return string
     */
    public static function hex2rgb($hex) {
        $hex = str_replace("#", "", $hex);

        if(strlen($hex) == 3) {
            $r = hexdec(substr($hex,0,1).substr($hex,0,1));
            $g = hexdec(substr($hex,1,1).substr($hex,1,1));
            $b = hexdec(substr($hex,2,1).substr($hex,2,1));
        } else {
            $r = hexdec(substr($hex,0,2));
            $g = hexdec(substr($hex,2,2));
            $b = hexdec(substr($hex,4,2));
        }
        $rgb = array($r, $g, $b);
        //return implode(",", $rgb); // returns the rgb values separated by commas
        return $rgb; // returns an array with the rgb values
    }

    /**
     * Converts HSL color to RGB hex code
     * Input: Array(Hue, Saturation, Lightness) - Values from 0 to 1
     * Output: String hex value (#000000 - #ffffff)
     * @param $hsl
     * @return string
     */
    public static function hsl2hex($hsl) {
        $rgb = self::hsl2rgb($hsl);
        return self::rgb2hex($rgb);
    }
}