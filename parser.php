<?php
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

use Bitrix\Main\ArgumentException;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\SystemException;

class Parser
{
    public static $url_page = 'https://imdvor.ru/catalog/gold/';
    public static $url_index = 'https://imdvor.ru';
    public static $imageCount = 0;

    public static function parsePage()
    {
        $response = self::getHTMLPage();

        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($response);

        $xpath = new DOMXPath($dom);

        $parsedData = [];
        $productNodes = $xpath->query('//div[contains(@class, "catalog_item_wrapp")]');

        foreach ($productNodes as $productNode) {
            $titleNode = $xpath->query('.//div[contains(@class, "item-title")]/a/span', $productNode)->item(0);
            $title = $titleNode ? trim($titleNode->textContent) : '';

            $imageSrcNode = $xpath->query('.//img[contains(@class, "img-responsive")]/@src', $productNode)->item(0);
            $imageSrc = $imageSrcNode ? self::$url_page . $imageSrcNode->nodeValue : '';

            $image = self::downloadImage($imageSrc);

            $linkNode = $xpath->query('.//div[contains(@class, "item-title")]/a', $productNode)->item(0);
            $link = $linkNode ? self::$url_index . $linkNode->getAttribute('href') : '';

            $priceNode = $xpath->query('.//div[contains(@class, "price_value_block")]', $productNode)->item(0);
            $price = $priceNode ? self::formatPrice($priceNode->textContent) : '';

            $parsedData[] = [
                'title' => $title,
                'image' => $image,
                'link' => $link,
                'price' => $price,
            ];
        }

        echo 'Количество загруженных изображений: ' . self::$imageCount;

        self::saveToHL($parsedData);

        return $parsedData;
    }

    private static function formatPrice($price)
    {
        $price = str_replace(' ', '', $price); // Удаляем пробелы
        $price = str_replace('руб.', '', $price); // Удаляем "руб."

        return $price;
    }

    private static function downloadImage($url)
    {
        $filename = basename($url);
        $savePath = $_SERVER['DOCUMENT_ROOT'] . '/upload/' . $filename;

        $curl = curl_init($url);
        $fp = fopen($savePath, 'wb');
        curl_setopt($curl, CURLOPT_FILE, $fp);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_exec($curl);
        curl_close($curl);
        fclose($fp);

        self::$imageCount++;

        return '/upload/' . $filename;
    }

    public static function getHTMLPage()
    {
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => self::$url_page,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/72.0.3626.121 Safari/537.36',
        ));

        $response = curl_exec($curl);
        curl_close($curl);

        return $response;
    }

    public static function saveToHL($data)
    {
        if (!Bitrix\Main\Loader::includeModule('highloadblock')) {
            throw new SystemException('Модуль highloadblock не установлен');
        }

        $hlBlockId = 2;

        if (empty($hlBlockId)) {
            throw new ArgumentException('ID highload-блока не указан');
        }

        $hlBlock = \Bitrix\Highloadblock\HighloadBlockTable::getById($hlBlockId)->fetch();

        if (!$hlBlock) {
            throw new ObjectPropertyException('Highload-блок не найден');
        }

        $entity = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity($hlBlock);
        $entityDataClass = $entity->getDataClass();

        foreach ($data as $item) {
            $fields = array(
                'UF_NAME' => $item['title'],
                'UF_IMG' => \CFile::MakeFileArray($_SERVER['DOCUMENT_ROOT'] . $item['image']),
                'UF_LINK' => $item['link'],
                'UF_PRICE' => $item['price'],
            );

            $entityDataClass::add($fields);
        }
    }

}
