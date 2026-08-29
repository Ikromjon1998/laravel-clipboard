<?php

declare(strict_types=1);

/**
 * Draws the application icon and packs it into an .icns.
 *
 * Generated rather than committed as a binary so the design lives in code and
 * can be adjusted in one place. macOS renders app icons on the "squircle"
 * grid: the artwork sits inside a rounded square that occupies roughly 80% of
 * the canvas, with the remaining margin left transparent.
 */
final class AppIcon
{
    private const CANVAS = 1024;

    /** Apple's grid: the rounded square covers ~80% of the canvas. */
    private const INSET = 0.10;

    private const CORNER = 0.225;

    public static function build(string $root): int
    {
        $iconset = $root.'/build/AppIcon.iconset';

        // The build copies icons out of public/, not resources/ — see
        // NativePHP's InstallsAppIcon trait. Written to both so the packaged
        // app and anything referencing them at runtime agree.
        $icns = $root.'/public/icon.icns';
        $png = $root.'/public/icon.png';

        @mkdir($iconset, 0o755, recursive: true);
        @mkdir($root.'/resources/icons', 0o755, recursive: true);

        $master = self::draw(self::CANVAS);

        imagepng($master, $png);

        // The sizes iconutil expects; anything missing makes it refuse.
        $sizes = [16, 32, 64, 128, 256, 512, 1024];

        foreach ($sizes as $size) {
            $scaled = self::resize($master, $size);

            if ($size <= 512) {
                imagepng($scaled, sprintf('%s/icon_%dx%d.png', $iconset, $size, $size));
            }

            if ($size >= 32) {
                imagepng($scaled, sprintf('%s/icon_%dx%d@2x.png', $iconset, $size / 2, $size / 2));
            }

            imagedestroy($scaled);
        }

        imagedestroy($master);

        copy($png, $root.'/resources/icons/icon.png');

        // The menu bar mark travels under the name the build expects.
        foreach ([['menuBarIconTemplate.png', 'IconTemplate.png'], ['menuBarIconTemplate@2x.png', 'IconTemplate@2x.png']] as [$from, $to]) {
            if (is_file($root.'/resources/icons/'.$from)) {
                copy($root.'/resources/icons/'.$from, $root.'/public/'.$to);
            }
        }

        exec(sprintf('iconutil -c icns %s -o %s 2>&1', escapeshellarg($iconset), escapeshellarg($icns)), $out, $status);

        if ($status !== 0) {
            fwrite(STDOUT, "  Could not pack the icon: ".implode(' ', $out)."\n");

            return 1;
        }

        fwrite(STDOUT, '  Built '.basename($icns).' ('.number_format((int) filesize($icns) / 1024, 0)." KB)\n");

        return 0;
    }

    private static function draw(int $size): \GdImage
    {
        $image = imagecreatetruecolor($size, $size);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
        imagealphablending($image, true);

        $inset = (int) round($size * self::INSET);
        $box = $size - ($inset * 2);
        $radius = (int) round($box * self::CORNER);

        // A vertical gradient reads as depth at small sizes far better than a
        // flat fill, without needing a light source or a bevel.
        for ($y = 0; $y < $box; $y++) {
            $t = $y / max(1, $box - 1);
            $colour = imagecolorallocate(
                $image,
                (int) round(226 + (170 - 226) * $t),
                (int) round(74 + (38 - 74) * $t),
                (int) round(58 + (30 - 58) * $t),
            );
            imageline($image, $inset, $inset + $y, $inset + $box, $inset + $y, $colour);
        }

        self::roundCorners($image, $inset, $inset, $inset + $box, $inset + $box, $radius);
        self::drawClipboard($image, $inset, $box);

        return $image;
    }

    /** Knock transparent corners out of the filled square. */
    private static function roundCorners(\GdImage $image, int $x1, int $y1, int $x2, int $y2, int $radius): void
    {
        imagealphablending($image, false);
        $clear = imagecolorallocatealpha($image, 0, 0, 0, 127);

        $corners = [[$x1, $y1, 1, 1], [$x2, $y1, -1, 1], [$x1, $y2, 1, -1], [$x2, $y2, -1, -1]];

        foreach ($corners as [$cx, $cy, $dx, $dy]) {
            $centreX = $cx + ($dx * $radius);
            $centreY = $cy + ($dy * $radius);

            for ($x = 0; $x <= $radius; $x++) {
                for ($y = 0; $y <= $radius; $y++) {
                    $px = $cx + ($dx * $x);
                    $py = $cy + ($dy * $y);
                    $distance = sqrt((($px - $centreX) ** 2) + (($py - $centreY) ** 2));

                    if ($distance > $radius) {
                        imagesetpixel($image, $px, $py, $clear);
                    }
                }
            }
        }

        imagealphablending($image, true);
    }

    /** The same clipboard mark as the menu bar icon, in white. */
    private static function drawClipboard(\GdImage $image, int $inset, int $box): void
    {
        $white = imagecolorallocate($image, 255, 255, 255);
        $unit = $box / 22;
        $x = fn (float $u): int => (int) round($inset + ($u * $unit));

        $rounded = function (int $x1, int $y1, int $x2, int $y2, int $r, int $colour) use ($image): void {
            imagefilledrectangle($image, $x1 + $r, $y1, $x2 - $r, $y2, $colour);
            imagefilledrectangle($image, $x1, $y1 + $r, $x2, $y2 - $r, $colour);
            foreach ([[$x1 + $r, $y1 + $r], [$x2 - $r, $y1 + $r], [$x1 + $r, $y2 - $r], [$x2 - $r, $y2 - $r]] as [$cx, $cy]) {
                imagefilledellipse($image, $cx, $cy, $r * 2, $r * 2, $colour);
            }
        };

        $stroke = (int) round(1.5 * $unit);
        $board = (int) round(2.4 * $unit);

        // Board outline, then the inside knocked back out to leave a stroke.
        $rounded($x(4.6), $x(5.0), $x(17.4), $x(18.4), $board, $white);
        imagealphablending($image, false);
        self::cutInterior($image, $x(4.6) + $stroke, $x(5.0) + $stroke, $x(17.4) - $stroke, $x(18.4) - $stroke, max(1, $board - $stroke), $inset, $box);
        imagealphablending($image, true);

        // The clip at the top.
        $rounded($x(8.2), $x(3.0), $x(13.8), $x(6.6), (int) round(1.2 * $unit), $white);

        // Two lines of "content".
        $lineHeight = max(1, (int) round(1.3 * $unit));
        foreach ([[10.2, 14.4], [13.0, 12.8]] as [$top, $right]) {
            $rounded($x(7.6), $x($top), $x($right), $x($top) + $lineHeight, (int) round($lineHeight / 2), $white);
        }
    }

    /** Re-fill the board interior with the gradient behind it. */
    private static function cutInterior(\GdImage $image, int $x1, int $y1, int $x2, int $y2, int $radius, int $inset, int $box): void
    {
        for ($y = $y1; $y <= $y2; $y++) {
            $t = ($y - $inset) / max(1, $box - 1);
            $colour = imagecolorallocate(
                $image,
                (int) round(226 + (170 - 226) * $t),
                (int) round(74 + (38 - 74) * $t),
                (int) round(58 + (30 - 58) * $t),
            );

            for ($x = $x1; $x <= $x2; $x++) {
                $dx = max($x1 + $radius - $x, $x - ($x2 - $radius), 0);
                $dy = max($y1 + $radius - $y, $y - ($y2 - $radius), 0);

                if ((($dx ** 2) + ($dy ** 2)) <= ($radius ** 2)) {
                    imagesetpixel($image, $x, $y, $colour);
                }
            }
        }
    }

    private static function resize(\GdImage $source, int $size): \GdImage
    {
        $out = imagecreatetruecolor($size, $size);
        imagealphablending($out, false);
        imagesavealpha($out, true);
        imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));
        imagecopyresampled($out, $source, 0, 0, 0, 0, $size, $size, self::CANVAS, self::CANVAS);

        return $out;
    }
}

exit(AppIcon::build(dirname(__DIR__)));
