<?php
/**
 * Plugin Name: Persian Text and Date Converter
 * Description: به‌طور خودکار حروف عربی، اعداد و تاریخ میلادی را در محتوای وردپرس، نظرات، صفحات و عناوین به فارسی تبدیل می‌کند بدون آنکه ساختار کدهای HTML یا دیتابیس دستکاری شود.
 * Version: 2.0.0
 * Author: Saeed Tosifyan
 * Author URI: https://linkedin.com/in/saeedtx
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: persian-text-and-date-converter
 * Requires at least: 5.3
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit; // خروج در صورت دسترسی مستقیم
}

/**
 * تبدیل حروف عربی و اعداد تنها در متن خام
 * (حفظ سلامت تگ‌های HTML، اسکریپت‌ها، شورت‌کدها و لینک‌ها)
 */
function ptdc_convert_text($content) {
    if (empty($content) || is_admin() || is_feed() || (function_exists('wp_is_json_request') && wp_is_json_request())) {
        return $content;
    }

    // تفکیک بخش‌های حساس شامل کامنت‌های HTML، اسکریپت‌ها، استایل‌ها، بلاک‌های کد، شورت‌کدها و تگ‌های HTML
    $pattern = '/(
        <!--.*?-->                                                            # کامنت‌های HTML و گوتنبرگ
        |<(?:script|style|pre|code|svg)[^>]*>.*?<\/(?:script|style|pre|code|svg)> # بلوک‌های کد، اسکریپت و SVG
        |<[^>]+>                                                              # سایر تگ‌های HTML
        |\[[^\]]+\]                                                           # شورت‌کدهای وردپرس
    )/xis';

    $parts = preg_split($pattern, $content, -1, PREG_SPLIT_DELIM_CAPTURE);

    if (!is_array($parts)) {
        return $content;
    }

    // حروف عربی که باید اصلاح شوند (حفظ ئ و ؤ به دلیل کاربرد در فارسی)
    $arabic_chars  = ['ي', 'ك', 'ة'];
    $persian_chars = ['ی', 'ک', 'ه'];

    // اعداد انگلیسی و عربی به فارسی
    $en_arabic_numbers = [
        '0', '1', '2', '3', '4', '5', '6', '7', '8', '9',
        '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'
    ];
    $persian_numbers = [
        '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹',
        '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'
    ];

    foreach ($parts as &$part) {
        // نادیده گرفتن بخش‌های محافظت‌شده (تگ‌ها، شورت‌کدها و کامنت‌ها)
        if (empty($part) || $part[0] === '<' || $part[0] === '[') {
            continue;
        }

        // تفکیک آدرس‌های خام وب (URLها) در متن تا اعداد داخل دامنه و مسیر آسیب نبینند
        $url_pattern = '/(https?:\/\/[^\s<"\'\]]+)/ui';
        $sub_parts = preg_split($url_pattern, $part, -1, PREG_SPLIT_DELIM_CAPTURE);

        if (is_array($sub_parts)) {
            foreach ($sub_parts as &$sub_part) {
                if (!preg_match('/^https?:\/\//i', $sub_part)) {
                    $sub_part = str_replace($arabic_chars, $persian_chars, $sub_part);
                    $sub_part = str_replace($en_arabic_numbers, $persian_numbers, $sub_part);
                }
            }
            $part = implode('', $sub_parts);
        }
    }

    return implode('', $parts);
}

/**
 * الگوریتم تبدیل تاریخ میلادی به شمسی
 */
function ptdc_gregorian_to_jalali($gy, $gm, $gd) {
    $g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
    $jy = ($gy <= 1600) ? 0 : 979;
    $gy -= ($gy <= 1600) ? 0 : 1600;
    $gy2 = ($gm > 2) ? $gy + 1 : $gy;
    $days = 365 * $gy + (int)(($gy2 + 3) / 4) - (int)(($gy2 + 99) / 100) + (int)(($gy2 + 399) / 400) - 80 + $gd + $g_d_m[$gm - 1];
    
    $jy += 33 * (int)($days / 12053);
    $days %= 12053;
    $jy += 4 * (int)($days / 1461);
    $days %= 1461;
    $jy += (int)(($days - 1) / 365);
    
    if ($days > 365) {
        $days = ($days - 1) % 365;
    }
    
    $jm = ($days < 186) ? 1 + (int)($days / 31) : 7 + (int)(($days - 186) / 30);
    $jd = 1 + (($days < 186) ? ($days % 31) : (($days - 186) % 30));
    
    return [$jy, $jm, $jd];
}

/**
 * فرمت‌بندی تاریخ شمسی مطابق با الگوهای تاریخ وردپرس
 */
function ptdc_format_jalali($timestamp, $format = '', $fallback = '') {
    if (empty($format)) {
        $format = get_option('date_format');
    }

    // عدم تغییر فرمت‌های استاندارد ماشینی و سئو (جلوگیری از خرابی نقشه سایت و فیدها)
    $system_formats = ['c', 'r', 'U', DATE_W3C, DATE_ATOM, DATE_RSS];
    if (in_array($format, $system_formats, true)) {
        return !empty($fallback) ? $fallback : date($format, (int)$timestamp);
    }

    if (!is_numeric($timestamp)) {
        $timestamp = current_time('timestamp');
    }

    $timezone = wp_timezone();
    $datetime = new DateTime("@{$timestamp}");
    $datetime->setTimezone($timezone);

    $gy = (int)$datetime->format('Y');
    $gm = (int)$datetime->format('n');
    $gd = (int)$datetime->format('j');
    $w  = (int)$datetime->format('w'); // روز هفته: ۰ (یکشنبه) تا ۶ (شنبه)

    list($jy, $jm, $jd) = ptdc_gregorian_to_jalali($gy, $gm, $gd);

    $months = [
        1 => 'فروردین', 2 => 'اردیبهشت', 3 => 'خرداد',
        4 => 'تیر', 5 => 'مرداد', 6 => 'شهریور',
        7 => 'مهر', 8 => 'آبان', 9 => 'آذر',
        10 => 'دی', 11 => 'بهمن', 12 => 'اسفند'
    ];

    $days_of_week = [
        0 => ['یکشنبه', 'ی'],
        1 => ['دوشنبه', 'د'],
        2 => ['سه‌شنبه', 'س'],
        3 => ['چهارشنبه', 'چ'],
        4 => ['پنج‌شنبه', 'پ'],
        5 => ['جمعه', 'ج'],
        6 => ['شنبه', 'ش'],
    ];

    $replacements = [
        'Y' => sprintf('%04d', $jy),
        'y' => sprintf('%02d', $jy % 100),
        'm' => sprintf('%02d', $jm),
        'n' => (string)$jm,
        'F' => $months[$jm],
        'M' => $months[$jm],
        'd' => sprintf('%02d', $jd),
        'j' => (string)$jd,
        'l' => $days_of_week[$w][0],
        'D' => $days_of_week[$w][1],
    ];

    $output = '';
    $len = strlen($format);
    $escaped = false;

    for ($i = 0; $i < $len; $i++) {
        $char = $format[$i];

        if ($escaped) {
            $output .= $char;
            $escaped = false;
            continue;
        }

        if ($char === '\\') {
            $escaped = true;
            continue;
        }

        if (isset($replacements[$char])) {
            $output .= $replacements[$char];
        } else {
            $output .= $datetime->format($char);
        }
    }

    return ptdc_convert_text($output);
}

/**
 * فیلترهای نمایش متون (Run-time Filters بدون لمس دیتابیس)
 */
add_filter('the_content', 'ptdc_convert_text', 20);
add_filter('the_title', 'ptdc_convert_text', 20);
add_filter('the_excerpt', 'ptdc_convert_text', 20);
add_filter('comment_text', 'ptdc_convert_text', 20);
add_filter('get_the_archive_title', 'ptdc_convert_text', 20);

/**
 * فیلتر تاریخ‌های وردپرس
 */
add_filter('wp_date', function($formatted, $format, $timestamp, $timezone) {
    if (is_admin() || is_feed() || (function_exists('wp_is_json_request') && wp_is_json_request())) {
        return $formatted;
    }
    return ptdc_format_jalali($timestamp, $format, $formatted);
}, 10, 4);

add_filter('date_i18n', function($date, $format, $timestamp, $gmt) {
    if (is_admin() || is_feed() || (function_exists('wp_is_json_request') && wp_is_json_request())) {
        return $date;
    }
    return ptdc_format_jalali($timestamp, $format, $date);
}, 10, 4);
