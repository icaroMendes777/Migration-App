<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;

use App\Models\Post;
use App\Models\FailedFiles;
use App\Models\Collections;
use App\Models\Redirect;
use App\Models\wp_posts;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use App\Models\wp_term_relationships;
use App\Models\wp_term_taxonomy;
use App\Models\wp_terms;
use Illuminate\Database\Eloquent\Builder;

class Migration extends Controller
{

    /**
     * Abre os arquivos, lê os conteúdos
     * separa os títulos baseado na hierarquia html,
     * distingue as categorias e insere em um banco de dados.
     * Posteriormente, esses textos serão importados para um formato de wordpress
     *
     */
    public function migrateDatabase()
    {
        //evita erro de timeout
        set_time_limit(0);
        $folderSuttas = 'sutta/';

        $folderTexts = 'arquivo_textos_theravada/';

        echo 'started...<br/><br/>';

        echo 'Suttas: <br/>';

        $this->migrateSuttasStage1($folderSuttas);

        echo 'Textos: <br/>';

        $this->migrateTextsStage1($folderTexts);

        echo '<h3> Here we are done </h3>';

        echo 'Agora olhe no banco de dados e dê o nome mais apropriado para cada coleção se desejar';

        return;
    }




    /**
     *
     * depois dos posts terem sido migrados de arquivos para tabelas simples,
     * agora migramos para tabelas do formato wordpress:
     * As tabelas wp_posts, wp_terms, wp_term_relationships e wp_term_taxonomy
     *
     */
    public function migrateWordpress()
    {

        $limit = 3000; //limitar para testes

        set_time_limit(0);
        /**
         * lembrar de dar update count taxonomy
         */

        echo '<h3>Migrating Posts to wordpress</h3>';

        $posts = Post::get()->all();


        $count = 0;

        foreach ($posts as $post) {

            $count++;

            $postTitle = $post['title_pali'] ?? $post['title_pt'];

            $postContent = $post['text'];

            $postOldUrl = $post['old_url'];

            $colection = Collections::find($post['collection_id']);
            $colectionName = $colection['full_name'] ?? $colection['name'];

            $postSlug = $this->makePostSlug($post, $colectionName);


            $wpPost = wp_posts::create([
                'post_author' => 1,
                'post_date' => '2023-09-01 12:38:48',
                'post_date_gmt' => '2023-09-01 12:38:48',
                'post_content' => $postContent,
                'post_title' => $postTitle,
                'post_excerpt' => '',
                'post_status' => 'publish',
                'comment_status' => 'closed',
                'ping_status' => 'closed',
                'post_password' => '',
                'post_name' => $postSlug,
                'to_ping' => '',
                'pinged' => '',
                'post_modified' => '2023-09-01 12:38:48',
                'post_modified_gmt' => '2023-09-01 12:38:48',
                'post_content_filtered' => '',
                'post_parent' => 0,
                'guid' => 'migration:' . $postSlug . '-' . Carbon::now(),
                'menu_order' => 0,
                'post_type' => 'post',
            ]);


            // a partir daqui insere as categorias e tags



            try {

                if ($post['title_pali']) // nesse caso é um sutta
                {
                    $categorySlug = 'suttas';
                    $this->createRelation($wpPost['id'], 'Suttas', 'category');
                    $this->createRelation($wpPost['id'], 'Suttas', 'post_tag');

                    $this->createRelation($wpPost['id'], $colectionName, 'post_tag');
                } else {
                    $categorySlug = 'textos-theravada';
                    $this->createRelation($wpPost['id'], 'Textos Theravada', 'category');
                    $this->createRelation($wpPost['id'], 'Textos Theravada', 'post_tag');
                }

                Redirect::create([
                    'old_url' => $postOldUrl,
                    'new_url' => $categorySlug . '/' . $postSlug
                ]);
            } catch (QueryException $e) {
                // Log the error
                $error = $this->truncateString($e->getMessage(), 500);

                echo 'erro: ' . $error . '<br/>';
                //$this->errorLog($filePath, 'no insertion: '.$error);

            }

            if ($count == $limit) return;

            if ($count % 200) echo `-$count ok. <br/>`;
        }


        echo '<h4>Finished.</h4>';

        return;
    }


    /**
     * Por último mas não menos importante,
     * geramos as linhas de redirecionamento para o .htaccess
     * com isso mantemos as referências dos últimos posts antes da migração
     */
    public function generateRedirects()
    {

        $redirects = Redirect::all();

        $myfile = fopen('export.txt', "w") or die("Unable to open file!");

        foreach ($redirects as $redirect) {

            $old = $redirect['old_url'];
            $new = $redirect['new_url'];
            $line = "Redirect 301 /" . $old . " /" . $new . "\n";
            fwrite($myfile, $line);
        }

        fclose($myfile);
    }

    public function makePostSlug($post, $colectionName)
    {

        $postTitle = $post['title_pali'] ?? $post['title_pt'];

        if ($post['title_pali']) {
            $postSlug = Str::slug($colectionName) . '-' . Str::slug($post['title_pali'] . '-' . $post['title_pt']);
        } else {
            $postSlug = Str::slug($colectionName) . '-' . Str::slug($postTitle);
        }

        $postSlug = substr($postSlug, 0, 200);

        return $postSlug;
    }

    /**
     * a relação pode ser:'category' or 'post_tag'
     */
    public function createRelation($wpPostId, $groupName, $relation)
    {

        //echo '<p>Name: '.$groupName.', Relation: '.$relation.'</p>';

        $termTaxonomy = $this->getTermTaxonomy($groupName, $relation);

        if (!$termTaxonomy) $termTaxonomy = $this->createTermTaxonomy($groupName, $relation);

        $relation = $this->createTermRelationship($wpPostId, $termTaxonomy['term_taxonomy_id']);

        $this->increaseCountTermTaxonomy($termTaxonomy['term_taxonomy_id']);

        return $relation;
    }

    public function increaseCountTermTaxonomy($termTaxonomyId)
    {

        $termTaxonomy = wp_term_taxonomy::query()->where('term_taxonomy_id', $termTaxonomyId)->first();

        $count = $termTaxonomy['count'] + 1;
        wp_term_taxonomy::where('term_taxonomy_id', $termTaxonomyId)
            ->update(['count' => $count]);
    }



    public function createTermRelationship($postId, $termTaxonomyId)
    {
        return wp_term_relationships::create([
            'object_id' => $postId,
            'term_taxonomy_id' => $termTaxonomyId
        ]);
    }

    public function createTermTaxonomy($name, $taxonomy)
    {

        $slug = Str::slug($name);
        $term = wp_terms::create([
            'name' => $name,
            'slug' => $slug
        ]);

        wp_term_taxonomy::create(
            [
                'term_id' => $term['id'],
                'taxonomy' => $taxonomy,
                'description' => ''
            ]
        );

        $termTaxonomy = wp_term_taxonomy::query()
            ->where('term_id', $term['id'])
            ->get()
            ->first();


        /**
         * wp nomeia a id como term_taxonomy_id
         * mas quando esse campo vem do create ele vem nomado 'id'
         * quando vem do get, vem 'term_taxonomy_id'
         * por isso retornamos o get, não o create - para padronização
         */

        return $termTaxonomy;
    }

    public function getTermTaxonomy($name, $taxonomy)
    {

        //as relações em wp são dificeis de serem setadas no laravel
        //já que o id das tabelas não obedece o mesmo padrão

        $terms = wp_terms::query()
            ->where('name', $name)
            ->get();

        $match = null;

        if ($terms) {
            foreach ($terms as $term) {
                $match = wp_term_taxonomy::query()
                    ->where('term_id', $term['term_id'])
                    ->where('taxonomy', $taxonomy)
                    ->get()
                    ->first();
                if ($match) return $match;
            }
        }

        return $match;
    }


    public function migrateTextsStage1($dir)
    {
        $files = $this->getClearFileList($dir);

        $count = 0;

        foreach ($files as $f) {

            $filePath = $dir . $f;

            $count++;

            $textIndex = $this->getSuttaIndexFromFileName($f);
            $fileContent = file_get_contents($dir . '/' . $f);
            $cleanText = $this->correctEncoding($this->cleanText($fileContent));
            $title = $this->getTitleFromText($cleanText);

            if (!$title) {
                $this->errorLog($filePath, 'no title');

                continue;
            }

            $collectionId = $this->getCollectionId('Textos Theravada');

            $data = [
                'collection_id' => $collectionId,
                'old_url' => $filePath,
                'index' => $textIndex,
                'title_pt' => $title,
                'text' => $cleanText,

            ];

            try {
                $result = Post::create($data);
            } catch (QueryException $e) {
                $error = $this->truncateString($e->getMessage(), 220);
                $this->errorLog($filePath, 'no insertion: ' . $error);
            }

            if ($count == 10) print_r('10 files... <br/>');

            if ($count == 100) print_r('100 files... <br/>');

            if ($count == 170) print_r('170 files... <br/>');
        }
    }

    public function migrateSuttasStage1($dir)
    {
        $files = $this->getClearFileList($dir);

        $count = 0;

        foreach ($files as $f) {

            $filePath = $dir . $f;

            $count++;

            $suttaIndex = $this->getSuttaIndexFromFileName($f);
            $suttaCollection = $this->getSuttaCollectionFromIndex($suttaIndex);

            $fileContent = file_get_contents($dir . '/' . $f);

            $cleanText = $this->correctEncoding($this->cleanText($fileContent));

            $title = $this->getTitleFromText($cleanText);

            $translatedTitle = $this->getTranslatedTitle($cleanText);

            if (!$title) {
                $this->errorLog($filePath, 'no title');

                continue;
            }

            $collectionId = $this->getCollectionId($suttaCollection);

            $data = [
                'collection_id' => $collectionId,
                'old_url' => $filePath,
                'index' => $suttaIndex,
                'title_pt' => $translatedTitle,
                'title_pali' => $title,
                'text' => $cleanText,

            ];

            try {

                $result = Post::create($data);
            } catch (QueryException $e) {
                $error = $this->truncateString($e->getMessage(), 220);

                $this->errorLog($filePath, 'no insertion: ' . $error);
            }

            //log para ter noção do progresso rumo aos 2000 arquivos
            if ($count == 100) print_r('100 files... <br/>');

            if ($count == 1000) print_r('1000 files... <br/>');

            if ($count == 1700) print_r('1700 files... <br/>');
        }
    }

    public function errorLog($filePath, $message = '')
    {

        FailedFiles::create([
            'file_path' => $filePath,
            'error_message' => $message
        ]);
    }

    public function getCollectionId($collectionName)
    {
        $collection = Collections::query()
            ->where('name', $collectionName)
            ->get()
            ->first();

        if (!$collection)
            $collection = Collections::create([
                'name' => $collectionName,
            ]);

        return $collection['id'];
    }

    public function getTranslatedTitle($text)
    {
        $text1 = $this->extractLastSubstringBetween($text, 'class=Tit1', 'Tit1>');

        $title = $this->extractLastSubstringBetween($text1, '>', '<');

        return $title;
    }

    public function cleanText($text)
    {
        $text = $this->removePHP($text);
        $text = $this->removeNextSuttaLink($text);

        return $text;
    }

    /**
     * o texto que desejamos está dentro das tags:
     * 
     * '<!-- INICIO DO TEXTO -->', '<!-- FIM DO TEXTO -->'
     */
    public function removePHP($fileContent)
    {
        return $this->extractSubstringBetween($fileContent, '<!-- INICIO DO TEXTO -->', '<!-- FIM DO TEXTO -->');
    }

    public function getSuttaIndexFromFileName($fileName)
    {
        $name = str_replace('.php', '', $fileName);
        $name = str_replace('./files_to_migrate/', '', $name);
        return $name;
    }


    public function getSuttaCollectionFromIndex($index)
    {
        $collection = substr($index, 0, 2);

        return $collection;
    }


    public function getClearFileList($dir)
    {
        $listFiles = array_diff(scandir($dir), array('..', '.'));
        return $listFiles;
    }

    public function removeNextSuttaLink($text)
    {

        $tag = '<p class=Normal><a href=';

        // Find the position of the tag
        $tagPosition = strpos($text, $tag);

        // Extract the text before the tag
        if ($tagPosition !== false) {
            $extractedText = substr($text, 0, $tagPosition);
        } else {
            $extractedText = $text;
        }

        return $extractedText;
    }


    public function getTitleFromText($text)
    {

        $text1 = $this->extractSubstringBetween($text, 'class=Tit1', 'Tit1>');
        $title = $this->extractSubstringBetween($text1, '>', '<');

        return $title;
    }


    function extractSubstringBetween($text, $string1, $string2)
    {
        $startPos = strpos($text, $string1);
        if ($startPos !== false) {
            $startPos += strlen($string1);
            $endPos = strpos($text, $string2, $startPos);
            if ($endPos !== false) {
                return substr($text, $startPos, $endPos - $startPos);
            }
        }
        return false;
    }

    function extractLastSubstringBetween($text, $string1, $string2)
    {
        $endPos = strrpos($text, $string2);
        if ($endPos !== false) {
            $startPos = strrpos($text, $string1, $endPos - strlen($text));
            if ($startPos !== false) {
                $startPos += strlen($string1);
                return substr($text, $startPos, $endPos - $startPos);
            }
        }
        return false;
    }



    public function correctEncoding($text)
    {

        $originalText = $text;
        $convertedText = iconv('ISO-8859-1', 'UTF-8', $originalText);

        return $convertedText;
    }

    function truncateString($string, $length, $dots = "...")
    {
        return (strlen($string) > $length) ? substr($string, 0, $length - strlen($dots)) . $dots : $string;
    }




    private function debug($data)
    {
        echo '<pre>';
        //print_r($data);
        echo json_encode($data);
        echo '<pre>';
    }
}
