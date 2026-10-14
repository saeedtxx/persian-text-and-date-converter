<?php
/**
 * Plugin Name: Persian Text and Date Converter
 * Description: به‌طور خودکار حروف عربی، اعداد و تاریخ میلادی را در محتوای وردپرس، نظرات، صفحات و عناوین به فارسی تبدیل می‌کند بدون آنکه ساختار کدهای HTML یا دیتابیس دستکاری شود.
 * Version: 2.0.1
 * Author: Saeed Tosifyan
 * Author URI: https://linkedin.com/in/saeedtx
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: persian-text-and-date-converter
 * Requires at least: 5.3
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * بررسی درخواست‌های مدیریتی، فید، REST API و نقشه سایت
 */
function ptdc_should_skip_conversion() {
    if (is_admin() || is_feed() || (function_exists('wp_is_json_request') && wp_is_json_request())) {
        return true;
    }
    if (function_exists('wp_is_xml_request') && wp_is_xml_request()) {
        return true;
    }
    if ( ! empty( get_query_var( 'sitemap' ) ) || ! empty( get_query_var( 'sitemap-stylesheet' ) ) ) {
        return true;
    }
    return false;
}

/**
 * تبدیل حروف عربی و اعداد تنها در متن خام
 * (حفظ سلامت تگ‌های HTML، موجودیت‌های HTML، اسکریپت‌ها، بلاک‌های کد و لینک‌ها)
 */
function ptdc_convert_text($content) {
    if (empty($content) || !is_string($content) || ptdc_should_skip_conversion()) {
        return $content;
    }

    // تفکیک دقیق بخش‌های حساس بدون تداخل تگ‌های تودرتو و حفظ HTML Entities
    $pattern = '/(
        <!--.*?-->                                     # کامنت‌های HTML و گوتنبرگ
        |<script\b[^>]*>.*?<\/script>                  # تگ‌های اسکریپت
        |<style\b[^>]*>.*?<\/style>                    # تگ‌های استایل
        |<pre\b[^>]*>.*?<\/pre>                        # بلاک‌های pre
        |<code\b[^>]*>.*?<\/code>                      # بلاک‌های code
        |<svg\b[^>]*>.*?<\/svg>                        # تصاویر برداری SVG
        |<textarea\b[^>]*>.*?<\/textarea>              # فیلدهای متنی فرم
        |<kbd\b[^>]*>.*?<\/kbd>                        # کلیدهای صفحه‌کلید
        |<samp\b[^>]*>.*?<\/samp>                      # خروجی نمونه برنامه
        |<var\b[^>]*>.*?<\/var>                        # متغیرها
        |<math\b[^>]*>.*?<\/math>                      # فرمول‌های ریاضی
        |<[^>]+>                                       # سایر تگ‌های HTML
        |&(?:#\d+|#x[0-9a-fA-F]+|[a-zA-Z0-9]+);        # موجودیت‌های HTML (مثل &#8211; و &sup2;)
    )/xis';

    $parts = preg_split($pattern, $content, -1, PREG_SPLIT_DELIM_CAPTURE);

    if (!is_array($parts)) {
        return $content;
    }

    static $search_chars = [
        'ي', 'ك', 'ى',
        '0', '1', '2', '3', '4', '5', '6', '7', '8', '9',
        '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'
    ];
    static $replace_chars = [
        'ی', 'ک', 'ی',
        '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹',
        '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'
    ];

    // الگوی محافظت از لینک‌ها، ایمیل‌ها و عبارات فنی چسبیده به حروف لاتین (مثل MP3, H2O, PS5, v2.0)
    $sub_pattern = '/(
        https?:\/\/[^\s<"\'\]]+
        |[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}
        |\b[a-zA-Z]+[0-9][a-zA-Z0-9._-]*\b
        |\b[0-9]+[a-zA-Z][a-zA-Z0-9._-]*\b
    )/ux';

    foreach ($parts as $index => $part) {
        if ($part === '' || $part[0] === '<' || $part[0] === '&') {
            continue;
        }

        $sub_parts = preg_split($sub_pattern, $part, -1, PREG_SPLIT_DELIM_CAPTURE);

        if (is_array($sub_parts)) {
            foreach ($sub_parts as $sub_index => $sub_part) {
                // فقط بخش‌های زوج در preg_split با PREG_SPLIT_DELIM_CAPTURE متن عادی هستند
                if ($sub_index % 2 === 0 && $sub_part !== '') {
                    $sub_parts[$sub_index] = str_replace($search_chars, $replace_chars, $sub_part);
                }
            }
            $parts[$index] = implode('', $sub_parts);
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
 * بررسی کبیسه بودن سال شمسی
 */
function ptdc_is_jalali_leap($jy) {
    $rem = ($jy + 2346) % 2820;
    if ($rem < 0) {
        $rem += 2820;
    }
    return (((($rem + 38) * 31) % 128) < 31);
}

/**
 * فرمت‌بندی تاریخ شمسی مطابق با الگوهای تاریخ وردپرس
 */
function ptdc_format_jalali($timestamp, $format = '', $fallback = '', $timezone = null) {
    if (empty($format)) {
        $format = get_option('date_format');
    }

    // محافظت از فرمت‌های ماشینی، سئو، اسکیما و دیتابیس (ISO 8601, RFC, MySQL, W3C)
    $system_formats = ['c', 'r', 'U', 'Y-m-d', 'Y-m-d H:i:s', DATE_W3C, DATE_ATOM, DATE_RSS, DATE_RFC2822, DATE_RFC3339];
    if (in_array($format, $system_formats, true) || strpos($format, 'Y-m-d\T') === 0) {
        return !empty($fallback) ? $fallback : wp_date($format, (int)$timestamp, $timezone);
    }

    if (!is_numeric($timestamp)) {
        $timestamp = time();
    }

    if (!($timezone instanceof DateTimeZone)) {
        $timezone = wp_timezone();
    }

    $datetime = new DateTime("@{$timestamp}");
    $datetime->setTimezone($timezone);

    $gy = (int)$datetime->format('Y');
    $gm = (int)$datetime->format('n');
    $gd = (int)$datetime->format('j');
    $w  = (int)$datetime->format('w');

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

    $is_leap = ptdc_is_jalali_leap($jy);
    $days_in_month = ($jm <= 6) ? 31 : (($jm <= 11 || $is_leap) ? 30 : 29);
    $day_of_year = ($jm <= 6) ? (($jm - 1) * 31 + $jd - 1) : (186 + ($jm - 7) * 30 + $jd - 1);

    $replacements = [
        'Y' => sprintf('%04d', $jy),
        'o' => sprintf('%04d', $jy),
        'y' => sprintf('%02d', $jy % 100),
        'm' => sprintf('%02d', $jm),
        'n' => (string)$jm,
        'F' => $months[$jm],
        'M' => $months[$jm],
        'd' => sprintf('%02d', $jd),
        'j' => (string)$jd,
        'l' => $days_of_week[$w][0],
        'D' => $days_of_week[$w][1],
        'S' => 'ام',
        't' => (string)$days_in_month,
        'z' => (string)$day_of_year,
        'L' => $is_leap ? '1' : '0',
        'a' => ($datetime->format('a') === 'am') ? 'ق.ظ' : 'ب.ظ',
        'A' => ($datetime->format('A') === 'AM') ? 'قبل از ظهر' : 'بعد از ظهر',
    ];

    // پیمایش ایمن کاراکترهای UTF-8
    $chars = preg_split('//u', $format, -1, PREG_SPLIT_NO_EMPTY);
    if (!is_array($chars)) {
        return $fallback;
    }

    $output = '';
    $escaped = false;

    foreach ($chars as $char) {
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
        } elseif (preg_match('/^[a-zA-Z]$/', $char)) {
            $output .= $datetime->format($char);
        } else {
            $output .= $char;
        }
    }

    // تبدیل سریع اعداد تاریخ به فارسی
    static $en_nums = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
    static $fa_nums = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];

    return str_replace($en_nums, $fa_nums, $output);
}

/**
 * فیلترهای نمایش متون
 */
add_filter('the_content', 'ptdc_convert_text', 20);
add_filter('the_title', 'ptdc_convert_text', 20);
add_filter('the_excerpt', 'ptdc_convert_text', 20);
add_filter('comment_text', 'ptdc_convert_text', 20);
add_filter('get_the_archive_title', 'ptdc_convert_text', 20);

/**
 * فیلتر تاریخ وردپرس (wp_date در وردپرس ۵.۳+ پوشش‌دهنده اصلی get_the_date و date_i18n است)
 */
add_filter('wp_date', function($formatted, $format, $timestamp, $timezone) {
    if (ptdc_should_skip_conversion()) {
        return $formatted;
    }
    return ptdc_format_jalali($timestamp, $format, $formatted, $timezone);
}, 10, 4);
