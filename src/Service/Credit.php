<?php

namespace App\Service;

class Credit
{
    public static function writeColor($txt, $color="white")
    {
        echo sprintf("\e[%s;m%s\e[0m\n", self::getColor($color), $txt);
    }

    public static function getColor($color)
    {
        $colors = [
        'white'=>'1;37',
        'light-cyan'=>'1;36',
        'light-magenta'=>'1;35',
        'gray'=>'1;30',
    ];
        return isset($colors[$color]) ? $colors[$color] : $colors['white'];
    }


    public static function display()
    {
        self::writeColor("===========================================", "gray");
        self::writeColor("  Feature-Team management tool for Gitlab", "white");
        self::writeColor("-------------------------------------------", "gray");
        self::writeColor("  by Cyril PEREIRA", "light-cyan");
        self::writeColor("===========================================", "gray");
        echo "\n";
    }
}
