<?php
/**
 * @author Semenov Alexander <semenov@skeeks.com>
 * @link http://skeeks.com/
 * @copyright 2010 SkeekS (СкикС)
 * @date 15.04.2016
 */

namespace skeeks\cms\seo\controllers;

use skeeks\cms\models\CmsContent;
use skeeks\cms\models\CmsContentElement;
use skeeks\cms\models\Tree;
use skeeks\cms\seo\vendor\UrlHelper;
use yii\helpers\Url;
use yii\web\Controller;
use yii\web\Response;

/**
 * Class SitemapController
 * @package skeeks\cms\seo\controllers
 */
class SitemapController extends Controller
{
    /**
     * @return string
     */
    public function actionOnRequest()
    {
        ini_set("memory_limit", "512M");

        $result = [];

        $this->_addContents($result);
        $this->_addTrees($result);

        \Yii::$app->response->format = Response::FORMAT_XML;
        $this->layout = false;

        //Генерация sitemap вручную, не используем XmlResponseFormatter
        \Yii::$app->response->content = $this->render($this->action->id, [
            'data' => $result
        ]);

        return;
    }

    /**
     * @param $code
     * @param $page
     */
    public function actionContent($code, $page)
    {
        ini_set("memory_limit", "512M");

        $result = [];

        $this->_addElements($result, $code, $page);

        \Yii::$app->response->format = Response::FORMAT_XML;
        $this->layout = false;

        //Генерация sitemap вручную, не используем XmlResponseFormatter
        \Yii::$app->response->content = $this->render($this->action->id, [
            'data' => $result
        ]);

        return;

    }

    /**
     * @param array $data
     * @return $this
     */
    protected function _addTrees(&$data = [])
    {
        $query = Tree::find()->where(['cms_site_id' => \Yii::$app->skeeks->site->id]);

        if (\Yii::$app->seo->activeTree) {
            $query->andWhere(['active' => 'Y']);
        }

        if (\Yii::$app->seo->treeTypeIds) {
            $query->andWhere(['tree_type_id' => \Yii::$app->seo->treeTypeIds]);
        }

        $trees = $query->orderBy(['level' => SORT_ASC, 'priority' => SORT_ASC])->all();

        if ($trees) {
            /**
             * @var Tree $tree
             */
            foreach ($trees as $tree) {
                if (!$tree->redirect && !$tree->redirect_tree_id) {
                    $data[] =
                        [
                            "loc"     => $tree->absoluteUrl,
                            "lastmod" => $this->_lastMod($tree),
                        ];
                }
            }
        }

        return $this;
    }


    protected function _addContents(&$data = [])
    {

        if (!\Yii::$app->seo->contentIds) {
            return;
        }

        $query = CmsContent::find()
            ->select(['*', '(select count(*) FROM cms_content_element cce WHERE cce.content_id = cc.id AND cce.active = "Y" AND cce.published_at <= NOW()) as count'])
            ->from('cms_content cc')
            ->where(['id' => \Yii::$app->seo->contentIds]);

        $contents = $query->orderBy(['updated_at' => SORT_DESC, 'priority' => SORT_ASC])->all();

        //Добавление элементов в карту
        if ($contents) {
            /**
             * @var CmsContent $model
             */
            foreach ($contents as $model) {

                $pages = ceil($model->raw_row['count'] / \Yii::$app->seo->sitemap_content_element_page_size);

                for ($p = 1; $p <= $pages; $p++) {
                    $data[] = [
                        "loc"     => Url::to(['/seo/sitemap/content', 'code' => $model->code, 'page' => $p], true),
                        "lastmod" => $this->_lastMod($model),
                    ];
                }
            }
        }

        return $this;
    }

    /**
     * @param Tree $model
     * @return string
     */
    private function _lastMod($model)
    {
        $string = date("c", $model->updated_at);

        if (\Yii::$app->seo->sitemap_min_date && \Yii::$app->seo->sitemap_min_date > $model->updated_at) {
            $string = date("c", \Yii::$app->seo->sitemap_min_date);
        }

        return $string;
    }

    /**
     * @param array $data
     * @return $this
     */
    protected function _addElements(&$data = [], $contentCode, $page = 1)
    {
        if (!$cmsContent = CmsContent::findOne(['code' => $contentCode]))
            return;

        $query = CmsContentElement::find()
            ->joinWith('cmsTree')
            ->andWhere([Tree::tableName() . '.cms_site_id' => \Yii::$app->skeeks->site->id]);

        $query->andWhere(['content_id' => $cmsContent->id]);


        if (\Yii::$app->seo->activeContentElem) {
            $query->andWhere([CmsContentElement::tableName() . '.active' => 'Y']);
        }


        /**
         * calculate offset
         */
        $offset = 0;
        if ($page > 1) $offset = ($page - 1) * \Yii::$app->seo->sitemap_content_element_page_size;
        $query->offset($offset);
        $query->limit(\Yii::$app->seo->sitemap_content_element_page_size);

        $elements = $query->orderBy(['updated_at' => SORT_DESC, 'priority' => SORT_ASC])->all();

        //Добавление элементов в карту
        if ($elements) {
            /**
             * @var CmsContentElement $model
             */
            foreach ($elements as $model) {
                $data[] =
                    [
                        "loc"     => $model->absoluteUrl,
                        "lastmod" => $this->_lastMod($model),
                    ];
            }
        }

        return $this;
    }

    /**
     * @param array $data
     * @return $this
     */
    protected function _addAdditional(&$data = [])
    {
        $data[] = [
            'loc' => Url::to(['/cms/cms/index'], true)
        ];

        return $this;
    }

    /**
     * @param Tree $model
     * @return string
     */
    private function _calculatePriority($model)
    {
        $priority = '0.4';
        if ($model->level == 0) {
            $priority = '1.0';
        } else if ($model->level == 1) {
            $priority = '0.8';
        } else if ($model->level == 2) {
            $priority = '0.7';
        } else if ($model->level == 3) {
            $priority = '0.6';
        } else if ($model->level == 4) {
            $priority = '0.5';
        }

        return $priority;
    }
}
