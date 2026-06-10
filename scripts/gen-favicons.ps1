# One-off helper: regenerate the Nano favicon set from badge-dark-blue.
# Downscales the 1080x1080 source badge to every required PNG size and
# rebuilds favicon.ico (embedded PNGs at 16/32/48). Run from project root.

Add-Type -AssemblyName System.Drawing

$srcUrl = 'https://assets.nano.org/img/badge-dark-blue.png'
$outDir = Join-Path $PSScriptRoot '..\static\img\favicon\Nano'
$tmp    = Join-Path $env:TEMP 'badge-dark-blue-src.png'

Invoke-WebRequest -Uri $srcUrl -OutFile $tmp -ErrorAction Stop
$src = [System.Drawing.Image]::FromFile($tmp)

function New-Png([System.Drawing.Image]$src, [int]$size) {
    $bmp = New-Object System.Drawing.Bitmap($size, $size)
    $g = [System.Drawing.Graphics]::FromImage($bmp)
    $g.InterpolationMode  = [System.Drawing.Drawing2D.InterpolationMode]::HighQualityBicubic
    $g.SmoothingMode      = [System.Drawing.Drawing2D.SmoothingMode]::HighQuality
    $g.PixelOffsetMode    = [System.Drawing.Drawing2D.PixelOffsetMode]::HighQuality
    $g.CompositingQuality = [System.Drawing.Drawing2D.CompositingQuality]::HighQuality
    $g.DrawImage($src, 0, 0, $size, $size)
    $g.Dispose()
    return $bmp
}

$sizes = 16,32,57,60,64,70,72,76,96,114,120,144,150,152,160,180,192,310
foreach ($s in $sizes) {
    $bmp = New-Png $src $s
    $bmp.Save((Join-Path $outDir "favicon-$s.png"), [System.Drawing.Imaging.ImageFormat]::Png)
    $bmp.Dispose()
    "favicon-$s.png"
}

# Build a multi-image .ico from PNG-encoded frames (16/32/48). Modern
# browsers and Windows Vista+ read PNG-compressed icon directory entries.
$icoSizes = 16,32,48
$frames = foreach ($s in $icoSizes) {
    $bmp = New-Png $src $s
    $ms = New-Object System.IO.MemoryStream
    $bmp.Save($ms, [System.Drawing.Imaging.ImageFormat]::Png)
    $bmp.Dispose()
    [pscustomobject]@{ Size = $s; Bytes = $ms.ToArray() }
}

$ico = New-Object System.IO.MemoryStream
$bw  = New-Object System.IO.BinaryWriter($ico)
$bw.Write([uint16]0); $bw.Write([uint16]1); $bw.Write([uint16]$frames.Count)  # ICONDIR
$offset = 6 + (16 * $frames.Count)
foreach ($f in $frames) {
    $dim = if ($f.Size -ge 256) { 0 } else { $f.Size }
    $bw.Write([byte]$dim); $bw.Write([byte]$dim)   # width, height
    $bw.Write([byte]0); $bw.Write([byte]0)          # palette, reserved
    $bw.Write([uint16]1); $bw.Write([uint16]32)     # planes, bpp
    $bw.Write([uint32]$f.Bytes.Length)              # bytes in resource
    $bw.Write([uint32]$offset)                      # image offset
    $offset += $f.Bytes.Length
}
foreach ($f in $frames) { $bw.Write($f.Bytes) }
$bw.Flush()
[System.IO.File]::WriteAllBytes((Join-Path $outDir 'favicon.ico'), $ico.ToArray())
"favicon.ico ($($ico.Length) bytes)"

$src.Dispose()
Remove-Item $tmp -ErrorAction SilentlyContinue
