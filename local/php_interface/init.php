<?php
use Bitrix\Main\Page\Asset;
AddEventHandler('main', 'OnEpilog', function () {
    $url = $_SERVER['REQUEST_URI'];
    if (strpos($url, '/knowledge/') === 0)                // страница БЗ
    {
        $asset = Asset::getInstance();
        $asset->addCss('/local/js/kb_rating/kb_rating.css');
        $asset->addJs('/local/js/kb_rating/kb_rating.js');
    }
});