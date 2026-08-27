<?php

declare(strict_types=1);

namespace App\Helpers;

final class AvatarHelper
{
    public static function hashCode(string $name): int
    {
        $hash = 0;

        foreach (str_split($name) as $character) {
            $hash = (($hash << 5) - $hash + ord($character)) & 0xFFFFFFFF;

            if ($hash >= 0x80000000) {
                $hash -= 0x100000000;
            }
        }

        return abs($hash);
    }

    public static function digit(int $number, int $position): int
    {
        return (int) floor($number / (10 ** $position)) % 10;
    }

    public static function unit(int $number, int $range, ?int $position = null): int
    {
        $value = $number % $range;

        if ($position !== null && $position !== 0 && self::digit($number, $position) % 2 === 0) {
            return -$value;
        }

        return $value;
    }

    public static function contrast(string $hexColor): string
    {
        $hexColor = ltrim($hexColor, '#');
        $red = hexdec(substr($hexColor, 0, 2));
        $green = hexdec(substr($hexColor, 2, 2));
        $blue = hexdec(substr($hexColor, 4, 2));
        $yiq = (($red * 299) + ($green * 587) + ($blue * 114)) / 1000;

        return $yiq >= 128 ? '#000000' : '#FFFFFF';
    }

    public static function createBoringBeamAvatarSvg(string $seed, array $customColors = [], int $size = 36): string
    {
        $colors = $customColors !== [] ? $customColors : ['#92A1C6', '#146A7C', '#F0AB3D', '#C271B4', '#C20D90'];
        $number = self::hashCode($seed);
        $colorCount = count($colors);
        $wrapperColor = $colors[$number % $colorCount];
        $backgroundColor = $colors[($number + 13) % $colorCount];
        $preTranslateX = self::unit($number, 10, 1);
        $preTranslateY = self::unit($number, 10, 2);
        $wrapperTranslateX = $preTranslateX < 5 ? $preTranslateX + ($size / 9) : $preTranslateX;
        $wrapperTranslateY = $preTranslateY < 5 ? $preTranslateY + ($size / 9) : $preTranslateY;
        $wrapperRotate = self::unit($number, 360);
        $wrapperScale = 1 + (self::unit($number, (int) ($size / 12)) / 10);
        $isMouthOpen = self::digit($number, 2) % 2 === 0;
        $isCircle = self::digit($number, 1) % 2 === 0;
        $eyeSpread = self::unit($number, 5);
        $mouthSpread = self::unit($number, 3);
        $faceRotate = self::unit($number, 10, 3);
        $faceTranslateX = $wrapperTranslateX > ($size / 6)
            ? $wrapperTranslateX / 2
            : self::unit($number, 8, 1);
        $faceTranslateY = $wrapperTranslateY > ($size / 6)
            ? $wrapperTranslateY / 2
            : self::unit($number, 7, 2);
        $faceColor = self::contrast($wrapperColor);
        $mouth = $isMouthOpen
            ? sprintf(
                '<path d="M15 %s c2 1 4 1 6 0" stroke="%s" fill="none" stroke-linecap="round"/>',
                19 + $mouthSpread,
                $faceColor
            )
            : sprintf(
                '<path d="M13,%s a1,0.75 0 0,0 10,0" fill="%s"/>',
                19 + $mouthSpread,
                $faceColor
            );

        return sprintf(
            '<svg viewBox="0 0 36 36" fill="none" xmlns="http://www.w3.org/2000/svg">'
            . '<mask id="avatar-mask" maskUnits="userSpaceOnUse" x="0" y="0" width="36" height="36">'
            . '<rect width="36" height="36" rx="72" fill="#FFFFFF"/></mask>'
            . '<g mask="url(#avatar-mask)"><rect width="36" height="36" fill="%s"/>'
            . '<rect width="36" height="36" transform="translate(%s %s) rotate(%s 18 18) scale(%s)" fill="%s" rx="%s"/>'
            . '<g transform="translate(%s %s) rotate(%s 18 18)">%s'
            . '<rect x="%s" y="14" width="1.5" height="2" rx="1" fill="%s"/>'
            . '<rect x="%s" y="14" width="1.5" height="2" rx="1" fill="%s"/>'
            . '</g></g></svg>',
            $backgroundColor,
            $wrapperTranslateX,
            $wrapperTranslateY,
            $wrapperRotate,
            $wrapperScale,
            $wrapperColor,
            $isCircle ? $size : $size / 6,
            $faceTranslateX,
            $faceTranslateY,
            $faceRotate,
            $mouth,
            14 - $eyeSpread,
            $faceColor,
            20 + $eyeSpread,
            $faceColor
        );
    }
}
