<?php
/**
 * @author Semenov Alexander <semenov@skeeks.com>
 * @link http://skeeks.com/
 * @copyright 2010 SkeekS (СкикС)
 * @date 08.06.2015
 */
/* @var $this yii\web\View */
/* @var $data array */
echo <<<HTML
<?xml version="1.0" encoding="UTF-8"?>\n
HTML;
?>
<!--	Created by <?= \Yii::$app->cms->descriptor->name; ?> -->
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" 
        xmlns:news="http://www.google.com/schemas/sitemap-news/0.9">
    <?php foreach ($data as $item) : ?>
        <url>
            <loc><?= $item['loc']; ?></loc>
            <news:news>
                <news:publication>
                    <news:name><?=\Yii::$app->cms->cmsSite->name;?></news:name>
                    <news:language><?=\Yii::$app->multiLanguage->default_lang;?></news:language>
                </news:publication>
                <news:genres>PressRelease, Blog</news:genres>
                <news:publication_date><?=$item['published'];?></news:publication_date>
                <news:title>
                    <?=$item['title'];?>
                </news:title>
                <news:keywords>
                    <?=$item['keywords'];?>
                </news:keywords>
            </news:news>
        </url>
    <?php endforeach; ?>
</urlset>