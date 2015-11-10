<?php
/**
 * TERMINAL HUE: CONTROL YOUR HUE FROM THE COMMAND LINE.
 * LIGHTS. RETURN. ACTION.
 *
 * MIT LICENSE.
 */

namespace TerminalHue;

class TerminalHue {
    /**
     * Get the decoded saved config for the bridge/user.
     * @return bool|mixed array
     */
    public static function getConfig()
    {
        if (file_exists("config.txt")) {
            return json_decode(file_get_contents("config.txt"));
        }
        return false;
    }
}