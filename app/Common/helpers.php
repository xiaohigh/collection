<?php

//转码
function convert($html)
{
	$html = mb_convert_encoding($html, 'ISO-8859-1','UTF-8');
	$html = mb_convert_encoding($html, 'UTF-8','GBK');
	return $html;
}