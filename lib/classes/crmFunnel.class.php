<?php

class crmFunnel
{
    public static function getFunnelStageColor($open_color, $close_color, $index, $count)
    {
        $open_color = is_scalar($open_color) ? trim(strval($open_color)) : '';
        if (strlen($open_color) <= 0 || $open_color[0] != '#' || strlen($open_color) != 7) {
            $open_color = '#ffffff';
        }

        $close_color = is_scalar($close_color) ? trim(strval($close_color)) : '';
        if (strlen($close_color) <= 0 || $close_color[0] != '#' || strlen($close_color) != 7) {
            $close_color = '#ffffff';
        }

        if (!$count || $index > $count - 1) {
            throw new waException('Invalid stages count or stage number');
        }
        $o = substr($open_color, 1);
        $c = substr($close_color, 1);

        list($ro, $go, $bo) = array(hexdec($o[0].$o[1]), hexdec($o[2].$o[3]), hexdec($o[4].$o[5]));
        list($rc, $gc, $bc) = array(hexdec($c[0].$c[1]), hexdec($c[2].$c[3]), hexdec($c[4].$c[5]));

        $q = $count > 1 ? ($index / ($count - 1)) : 0;

        return '#'.sprintf('%02X', $ro - ($ro - $rc) * $q).sprintf('%02X', $go - ($go - $gc) * $q).sprintf('%02X', $bo - ($bo - $bc) * $q);

        /*
        $dark_r = ($dark_color >> 16) & 0xff;
        $light_r = ($light_color >> 16) & 0xff;
        $dark_g = ($dark_color >> 8) & 0xff;
        $light_g = ($light_color >> 8) & 0xff;
        $dark_b = ($dark_color) & 0xff;
        $light_b = ($light_color) & 0xff;

        $r = round($dark_r + ($light_r - $dark_r) * $stage_number / $stage_count);
        $g = round($dark_g + ($light_g - $dark_g) * $stage_number / $stage_count);
        $b = round($dark_b + ($light_b - $dark_b) * $stage_number / $stage_count);

        $color = ($r << 16) + ($g << 8) + $b;

        return sprintf('#%06X', $color);
        */
    }
}
