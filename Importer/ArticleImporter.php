<?php

namespace App\CustomPageModel\Importer;

use App\Client\Client;
use App\CommonCustomBase\Importer\AbstractImporter;
use App\CustomPageModel\Mapper\ArticleMapper;
use App\Entity\Bloc\CustomPageModel;
use App\Entity\Bloc\Page;
use App\Entity\Bloc\WidgetElement;
use App\Entity\General\Category;
use App\Entity\General\Tag;
use App\Entity\Remote\Security\User;
use App\Message\CustomPageBatchImportMessage;
use App\Serializer\SerializerGroupEnum;
use App\Service\MediaTypeService;
use App\Service\SessionContextEnum;
use App\Service\SessionContextService;
use Doctrine\Common\Collections\ArrayCollection;
use DOMDocument;
use DOMElement;
use DOMXPath;

class ArticleImporter extends AbstractImporter
{
    const CUSTOM_PAGE_MODEL_ID = 1;
    protected $importUrl = "https://cineverse.fr/export.php";
    public $toPersistCategories = [];
    public $toPersistTags = [];

    function importAll(bool $sequential = false): bool
    {
        $output = $this->getOutput();
        $output?->title("Starting Cineverse article import");

        $data = json_decode(file_get_contents($this->importUrl), true);
        $totalNumber = $data['total'];
        $limit = $data['limit'];
        $loadedCount = 0;
        $i = 0;
        $progess = $output?->createProgressBar($totalNumber);

        while ($loadedCount < $totalNumber) {
            $importBatchMessage = new CustomPageBatchImportMessage(self::CUSTOM_PAGE_MODEL_ID, $i);
            $this->bus->dispatch($importBatchMessage);
            $output?->note("Imported batch $i");
            $progess?->advance($limit);
            $loadedCount += $limit;
            $i++;
        }

        return true;
    }

    function import(mixed $id): bool
    {
        self::setupImport();
        SessionContextService::getSerializationContext()->add(SerializerGroupEnum::BridgeImport);

        $data = file_get_contents($this->importUrl . "?postId=$id");
        $this->importSingle($data);

        SessionContextService::getSerializationContext()->remove(SerializerGroupEnum::BridgeImport);

        return true;
    }

    function importBatch(int $batchId = 0): bool
    {
        self::setupImport();
        SessionContextService::getSerializationContext()->add(SerializerGroupEnum::BridgeImport);

        $client = new Client('https://cineverse.fr');
        $serializedData = $client->request("export.php?ff&page=$batchId", ['cineverse_import']);
        $deserializedData = json_decode($serializedData, true);

        foreach ($deserializedData['data'] as $data) {
            $this->importSingle(json_encode($data));
        }

        unset($deserializedData, $serializedData);
        SessionContextService::getSerializationContext()->remove(SerializerGroupEnum::BridgeImport);
        return true;
    }

    function importSingle($serializedData): Page
    {
        self::setupImport(); // Initialize context
        $output = $this->getOutput();

        // Process data
        $data = $this->processExternalData($serializedData);
        $this->entityManager->flush();
        $output?->note($data['ID']);


        // Find existing page or create new one
        $page = $this->entityManager->getRepository(Page::class)->findOneBy(['remoteId' => $data['ID']]);
        if (!$page) {
            $page = $this->getArticleCustomPageModel()->toPreview();
            $this->entityManager->persist($page);
        }

        $iframes = [];
        $i = 0;
        foreach ($data['iframes'] as $src => $service) {
            $i++;
            $iframes[] = (new WidgetElement())
                ->setBaseAdditionnalStyles(['rounded'])
                ->setDescription("<iframe frameborder='0' allow='accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share' allowfullscreen data-src='$src' data-name='$service'></iframe>")
                ->setOrdre($i);
        }
        $data['iframes'] = $iframes;
        unset($data['metas']);
        $data['authorId'] = $data['author']->getId();
        $mapper = new ArticleMapper($page, $data);
        $mapper->map();
        $page->setHidden(false)
            ->setImported(true);

        $output?->note($page->getSlug());
        $this->entityManager->flush();
        $this->entityManager->clear();

        return $page;
    }

    private function cleanWordPressContent($content)
    {
        $content = str_replace("\0", '', $content);
        $content = str_replace("\u{A0}", ' ', $content);
        $content = str_replace("\xC2\xA0", ' ', $content); // UTF-8 encoding de \u{A0}
        $content = preg_replace('/\x{00A0}/u', ' ', $content); // Regex Unicode
        $content = str_replace([
            "\u{200B}", // Zero width space
            "\u{200C}", // Zero width non-joiner
            "\u{200D}", // Zero width joiner
            "\u{FEFF}", // Byte order mark
        ], '', $content);
        $content = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $content);
        $content = mb_convert_encoding($content, 'UTF-8', 'UTF-8');
        $content = preg_replace('/\s+/', ' ', $content);
        $content = preg_replace('/<([^>]+)>/', '<$1>', $content);

        return trim($content);
    }

    public function processExternalData(string $serializedData): array
    {
        // Decode the data
        $data = json_decode($serializedData, true);

        $data['articleContent'] = $this->cleanWordPressContent($data['articleContent']);
        $data['iframes'] = $this->extractIframes($data['articleContent']);
        $data['articleContent'] = $this->stripHTMLTags(
            $data['articleContent'],
            ['a', 'strong', 'em', 'img', 'span', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'ul', 'li']
        );
        $data['articleContent'] = $this->cleanHTMLCode($data['articleContent']);
        $data['articleContent'] = preg_replace([
            '/\s*style=""/i',
            '/\s*style="text-align: justify;"/i',
            '/\s*style="text-align: center;"/i',
            '/\s*style="color: #000000;"/i',
        ], '', $data['articleContent']);

        $data['articleContent'] = $this->escapeContent($data['articleContent']);
        $data['articleContent'] = $this->autoformatDescription($data['articleContent']);

        // Clean up and format other data
        $data['thumbnail'] = trim($data['thumbnail'], '/');

        // Import the author
        $data['author'] = $this->importAuthor($data['authorId']);


        $data['createdAt'] = new \DateTime($data['createdAt']);
        $data['modifiedAt'] = new \DateTime($data['modifiedAt']);
        $data['tags'] = $this->getTagsEntities($data['tags']);
        $data['categories'] = $this->getCategoriesEntities($data['categories']);
        $data['viewCount'] = $data['metas']['views'][0] ?? 0;
        $data['remoteID'] = $data['ID'];

        $report = $this->imageImporterService->importImagesFrom($data['articleContent'], null, "articles/" . $data['ID']);
        foreach ($report['success'] as $success) {
            $data['articleContent'] = str_replace($success['baseMatch'], '/' . $success['relativePath'], $data['articleContent']);
            $data['articleContent'] = str_replace(
                trim(json_encode($success['baseMatch']), '"'),
                trim(json_encode('/' . $success['relativePath']), '"'),
                $data['articleContent']
            );
        }

        if (str_starts_with($data['thumbnail'], 'http')) {
            $report = $this->imageImporterService->importImagesFrom($data['thumbnail'], null, $data['ID']);
            foreach ($report['success'] as $success) {
                $data['thumbnail'] = '/' . $success['relativePath'];
            }
        }

        return $data;
    }

    /**
     * Import author using the UserImporter
     *
     * @param int $authorId Author ID to import
     * @return User|null Imported user entity
     */
    private function importAuthor(int $authorId): ?User
    {
//        $bridges = SessionContextService::getContext(SessionContextEnum::BRIDGES);
//
//        /** @var CustomPageBridgeInterface $bridge */
//        $bridge = array_values(array_filter($bridges, fn(CustomPageBridgeInterface $bridge) =>
//        array_key_exists("user", $bridge->getImporters())))[0];
//
//        $success = $bridge->getImporters()['user']->import($authorId);
//
//        if ($success) {
        // Return the user entity
        return $this->remoteEntityManager->getRepository(User::class)->findOneBy(['remoteId' => $authorId]);
//        }
//
//        return null;
    }

    public function getArticleCustomPageModel(): CustomPageModel
    {
        $customPageModel = $this->entityManager->getRepository(CustomPageModel::class)->find(self::CUSTOM_PAGE_MODEL_ID);
        if (!$customPageModel) {
            throw new \Exception("Le modèle de page n'a pas été trouvé : " . self::CUSTOM_PAGE_MODEL_ID);
        }

        return $customPageModel;
    }

    public function setupImport(): void
    {
        SessionContextService::setContext(SessionContextEnum::DEFAULT_LANGUAGE, 'FR');
        SessionContextService::setContext(SessionContextEnum::ACTUAL_LANGUAGE, 'FR');
        SessionContextService::setContext(SessionContextEnum::ACTUAL_MEDIA_TYPE, MediaTypeService::DEFAULT_MEDIA_TYPE);
        SessionContextService::setContext(SessionContextEnum::MANUAL_MEDIA_TYPE, MediaTypeService::DEFAULT_MEDIA_TYPE);
    }

    private function extractIframes(string $baseData): ?array
    {
        $doc = new DOMDocument();
        @$doc->loadHTML($baseData);
        $xpath = new DOMXPath($doc);

        $iframeElements = $xpath->query("//iframe");
        $results = [];

        foreach ($iframeElements as $iframe) {
            if ($iframe->hasAttribute('src')) {
                $src = $iframe->getAttribute('src');

                $parsedUrl = parse_url($src);
                $host = $parsedUrl['host'];
                $parts = explode('.', $host);

                // Déterminer le service en fonction du nom de domaine
                $service = '';
                if (count($parts) === 3 && $parts[0] !== 'open') {
                    // www.youtube.com => youtube
                    $service = $parts[1];
                } else if (count($parts) === 2 || (count($parts) === 3 && $parts[0] === 'open')) {
                    // spotify.com ou open.spotify.com => spotify
                    $service = $parts[count($parts) - 2];
                }

                $results[$src] = $service;
                unset($doc, $xpath);
            }
        }
        unset($doc, $xpath);

        return $results;
    }

    private function stripHTMLTags(string $data, array $stripAuthorizedTags): string
    {
        $doc = new DOMDocument();
        @$doc->loadHTML('<div>' . mb_convert_encoding($data, 'HTML-ENTITIES', 'UTF-8') . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        $scriptTags = $doc->getElementsByTagName('script');

        // On parcourt à l'envers pour éviter les problèmes d'indexation lors de la suppression
        for ($i = $scriptTags->length - 1; $i >= 0; $i--) {
            $scriptTags->item($i)->parentNode->removeChild($scriptTags->item($i));
        }

        $data = html_entity_decode($doc->saveHTML(), ENT_QUOTES, 'UTF-8');

        $data = str_replace(["\t", "\u", "\n"], '', $data);

        $data = str_replace(['h2', 'h1'], 'h3', $data);

        $data = strip_tags($data, $stripAuthorizedTags);

        unset($doc);
        return $data;
    }


    private function cleanHTMLCode(string $html): string
    {
        $attributesRemover = [
            'h3' => ['class'],
            'h4' => ['class'],
            'h5' => ['class'],
            'span' => ['class'],
            'p' => ['class'],
        ];

        $attributeKeeper = [
            'img' => ['src']
        ];

        $doc = new DOMDocument();
        @$doc->loadHTML(mb_convert_encoding("<div>" . $html . "</div>", 'HTML-ENTITIES', 'UTF-8'), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        foreach ($attributesRemover as $tag => $attributesToRemove) {
            $elements = $doc->getElementsByTagName($tag);
            foreach ($elements as $element) {
                foreach ($attributesToRemove as $attributeToRemove) {
                    $element->removeAttribute($attributeToRemove);
                }
            }
        }

        foreach ($attributeKeeper as $tag => $attributesToKeep) {
            $elements = $doc->getElementsByTagName($tag);
            foreach ($elements as $element) {
                $attributes = [];

                // Traitement spécial pour les images
                if ($tag === 'img') {
                    $srcValue = $this->getBestImageSrc($element);
                    if ($srcValue) {
                        $attributes['src'] = $srcValue;
                    }
                } else {
                    // Traitement normal pour les autres tags
                    foreach ($attributesToKeep as $attributeToKeep) {
                        $attributeValue = $element->getAttribute($attributeToKeep);
                        if ($attributeValue) {
                            $attributes[$attributeToKeep] = $attributeValue;
                        }
                    }
                }

                // Supprime tous les attributs existants
                while ($element->attributes->length > 0) {
                    $element->removeAttribute($element->attributes->item(0)->name);
                }

                // Remet seulement les attributs voulus
                foreach ($attributes as $attributeName => $attributeValue) {
                    $element->setAttribute($attributeName, $attributeValue);
                }
            }
        }

        $cleanHtml = $doc->saveHTML();
        $cleanHtml = html_entity_decode($cleanHtml, ENT_QUOTES, 'UTF-8');

        unset($doc);

        return $cleanHtml;
    }

    private function getBestImageSrc(DOMElement $imgElement): ?string
    {
        $currentSrc = $imgElement->getAttribute('src');
        $srcset = $imgElement->getAttribute('srcset') ?: $imgElement->getAttribute('data-srcset');
        if (!$srcset) {
            return $currentSrc ?: null;
        }

        $srcsetSources = $this->parseSrcset($srcset);
        if (empty($srcsetSources)) {
            return $currentSrc ?: null;
        } else {
            return $this->getLargestImageFromSrcset($srcsetSources);
        }
    }

    private function parseSrcset(string $srcset): array
    {
        $sources = [];
        $parts = explode(',', $srcset);

        foreach ($parts as $part) {
            $part = trim($part);
            if (empty($part)) continue;

            // Sépare l'URL de la taille (ex: "image.jpg 800w")
            if (preg_match('/^(.+?)\s+(\d+)w$/', $part, $matches)) {
                $sources[] = [
                    'url' => trim($matches[1]),
                    'width' => (int)$matches[2]
                ];
            } else {
                // Si pas de taille spécifiée, on assume que c'est une URL simple
                $sources[] = [
                    'url' => trim($part),
                    'width' => 0
                ];
            }
        }

        return $sources;
    }

    private function getLargestImageFromSrcset(array $sources): ?string
    {
        if (empty($sources)) {
            return null;
        }
        usort($sources, function ($a, $b) {
            return $b['width'] - $a['width'];
        });
        return $sources[0]['url'];
    }

    private function escapeContent(string $content): string
    {
        $patterns = [
            '/<h3>Partager.*/s' => '',
            '/#800080/s' => '#ce5a9b'
        ];

        foreach ($patterns as $pattern => $replace) {
            if (preg_match($pattern, $content)) {
                $content = preg_replace($pattern, $replace, $content);
            }
        }

        return $content;
    }

    private function autoformatDescription(string $html): string
    {
        $html = '<!DOCTYPE html><html><body>' . $html . '</body></html>';
        $html = preg_replace('/&(?![a-zA-Z0-9#]+;)/', '&amp;', $html);

        $doc = new \DOMDocument();
        @$doc->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));

        $xpath = new \DOMXPath($doc);

        // NOUVEAU : Cas 0 : Synchronisation des liens d'images
        // Si <a href="image1.jpg"><img src="image2.jpg"></a>, mettre href = src
        $anchorsWithImages = $xpath->query("//a[img]");
        foreach ($anchorsWithImages as $anchor) {
            $img = $anchor->getElementsByTagName('img')->item(0);
            if ($img) {
                $imgSrc = $img->getAttribute('src');
                $linkHref = $anchor->getAttribute('href');

                // Vérifie si le lien pointe vers une image (extension commune)
                if ($this->isImageUrl($linkHref) && $imgSrc && $imgSrc !== $linkHref) {
                    // Synchronise le lien avec l'image affichée
                    $anchor->setAttribute('href', $imgSrc);
                }
            }
        }

        // Cas 1 : <img>Text => <figure class="image"><img><figcaption class="credits">Text</figcaption></figure>
        $images = $doc->getElementsByTagName('img');
        $imagesToProcess = []; // Stocker les images pour éviter les problèmes d'itération
        foreach ($images as $image) {
            $imagesToProcess[] = $image;
        }

        foreach ($imagesToProcess as $image) {
            $nextSibling = $image->nextSibling;

            if ($nextSibling instanceof \DOMText && trim($nextSibling->wholeText) !== '') {
                // Créer figure
                $figure = $doc->createElement('figure');
                $figure->setAttribute('class', 'image');

                // Créer figcaption
                $figcaption = $doc->createElement('figcaption', htmlspecialchars(trim($nextSibling->wholeText)));
                $figcaption->setAttribute('class', 'credits');

                // Remplacer l'image par figure
                $parent = $image->parentNode;
                $clonedImage = $image->cloneNode(true);

                // Structure: <figure><img><figcaption></figcaption></figure>
                $figure->appendChild($clonedImage);
                $figure->appendChild($figcaption);

                // Remplacer l'image originale et le texte suivant
                $parent->replaceChild($figure, $image);
                if ($nextSibling->parentNode) {
                    $parent->removeChild($nextSibling);
                }
            }
        }

        // Cas 2 : <a><img></a>Text => <figure class="image"><a><img></a><figcaption class="credits">Text</figcaption></figure>
        $anchorsWithImages = $xpath->query("//a[img]");
        $anchorsToProcess = [];
        foreach ($anchorsWithImages as $anchor) {
            $anchorsToProcess[] = $anchor;
        }

        foreach ($anchorsToProcess as $anchor) {
            $nextSibling = $anchor->nextSibling;

            if ($nextSibling instanceof \DOMText && trim($nextSibling->wholeText) !== '') {
                // Créer figure
                $figure = $doc->createElement('figure');
                $figure->setAttribute('class', 'image');

                // Créer figcaption
                $figcaption = $doc->createElement('figcaption', htmlspecialchars(trim($nextSibling->wholeText)));
                $figcaption->setAttribute('class', 'credits');

                // Remplacer l'ancre par figure
                $parent = $anchor->parentNode;
                $clonedAnchor = $anchor->cloneNode(true);

                // Structure: <figure><a><img></a><figcaption></figcaption></figure>
                $figure->appendChild($clonedAnchor);
                $figure->appendChild($figcaption);

                // Remplacer l'ancre originale et le texte suivant
                $parent->replaceChild($figure, $anchor);
                if ($nextSibling->parentNode) {
                    $parent->removeChild($nextSibling);
                }
            }
        }

        // Cas 3 : <a><img></a><strong>Text</strong> => <figure class="image"><a><img></a><figcaption class="credits">Text</figcaption></figure>
        $anchorsWithStrong = $xpath->query("//a[img]/following-sibling::strong");
        $strongsToProcess = [];
        foreach ($anchorsWithStrong as $strong) {
            $strongsToProcess[] = $strong;
        }

        foreach ($strongsToProcess as $strong) {
            $anchor = $strong->previousSibling;

            if ($anchor instanceof \DOMElement && $anchor->nodeName === 'a') {
                // Créer figure
                $figure = $doc->createElement('figure');
                $figure->setAttribute('class', 'image');

                // Créer figcaption
                $figcaption = $doc->createElement('figcaption', htmlspecialchars(trim($strong->textContent)));
                $figcaption->setAttribute('class', 'credits');

                // Structure: <figure><a><img></a><figcaption></figcaption></figure>
                $parent = $anchor->parentNode;
                $clonedAnchor = $anchor->cloneNode(true);

                $figure->appendChild($clonedAnchor);
                $figure->appendChild($figcaption);

                // Remplacer l'ancre et le strong originaux
                $parent->replaceChild($figure, $anchor);
                if ($strong->parentNode) {
                    $parent->removeChild($strong);
                }
            }
        }

        // Récupération du contenu final
        $body = $doc->getElementsByTagName('body')->item(0);
        $innerHTML = '';
        foreach ($body->childNodes as $child) {
            $innerHTML .= $doc->saveHTML($child);
        }

        return $innerHTML;
    }

    private function isImageUrl(string $url): bool
    {
        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'tiff'];
        $extension = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));

        return in_array($extension, $imageExtensions);
    }


    private function getCategory(string $categoryName): Category
    {
        $category = $this->entityManager->getRepository(Category::class)->findOneBy(['name' => $categoryName]);

        if (!$category) {
            if (isset($this->toPersistCategories[$categoryName])) {
                return $this->toPersistCategories[$categoryName];
            }

            $category = (new Category())
                ->setName($categoryName)
                ->setTmp(false);
            $this->entityManager->persist($category);
            $this->toPersistCategories[$categoryName] = $category;
        }

        return $category;
    }

    private function getTag(string $tagName): Tag
    {
        $tag = $this->entityManager->getRepository(Tag::class)->findOneBy(['name' => $tagName]);
        if (!$tag) {
            if (isset($this->toPersistTags[$tagName])) {
                return $this->toPersistTags[$tagName];
            }
            $tag = (new Tag())
                ->setName($tagName)
                ->setTmp(false);
            $this->entityManager->persist($tag);
            $this->toPersistTags[$tagName] = $tag;
        }

        return $tag;
    }

    public function getCategoriesEntities(array $categoryNames): ArrayCollection
    {
        $categories = new ArrayCollection();
        foreach ($categoryNames as $categoryName) {
            $categories->add($this->getCategory($categoryName));
        }
        return $categories;
    }

    public function getTagsEntities(array $tagNames): ArrayCollection
    {
        $tags = new ArrayCollection();
        foreach ($tagNames as $tagName) {
            $tags->add($this->getTag($tagName));
        }
        return $tags;
    }
}