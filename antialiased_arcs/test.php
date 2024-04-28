<?php
/*
    
    Copyright (c) 2006-2008 Ulrich Mierendorff

    Permission is hereby granted, free of charge, to any person obtaining a
    copy of this software and associated documentation files (the "Software"),
    to deal in the Software without restriction, including without limitation
    the rights to use, copy, modify, merge, publish, distribute, sublicense,
    and/or sell copies of the Software, and to permit persons to whom the
    Software is furnished to do so, subject to the following conditions:

    The above copyright notice and this permission notice shall be included in
    all copies or substantial portions of the Software.

    THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
    IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
    FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL
    THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
    LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
    FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER
    DEALINGS IN THE SOFTWARE.

*/

include ("./imageSmoothArc_optimized.php");

$img = imageCreateTrueColor( 648, 800 );
imagealphablending($img,true);
$color = imageColorAllocate( $img, 255, 255, 255);
imagefill( $img, 5, 5, $color );

$y = 10;
for ($i = 2; $i < 32; $i+=2)
{
    for ($j = 0; $j < 10; $j++)
    {
        imageSmoothArc ( &$img, $i/2+10+$j*($i+10), $y, $i,$i, array($j/10.0*255,$j/10.0*255,$j/10.0*255,0), 0, 2*M_PI);
    }
    $y += $i + 4;
}

header( 'Content-Type: image/png' );
imagePNG( $img );

?>
