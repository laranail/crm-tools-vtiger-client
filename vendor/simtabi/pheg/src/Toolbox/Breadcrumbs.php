<?php declare(strict_types=1);

namespace Simtabi\Pheg\Toolbox;

final class Breadcrumbs
{

    /**
     * @var
     */
    private $base;

    /**
     * @var null|string
     */
    private ?string $separator;

    /**
     * @var null|string
     */
    private ?string $ulClass;

    /**
     * @var null|string
     */
    private ?string $ulAriaLabel;

    /**
     * @var null|string
     */
    private ?string $liClass;

    /**
     * @var null|string
     */
    private ?string $liActiveClass = 'active';

    /**
     * @var null|string
     */
    private ?string $liAriaCurrent = 'page';

    /**
     * @var null|string
     */
    private ?string $linkClass;

    /**
     * @var array
     */
    private array $links;

    /**
     * @var boolean
     */
    private bool $fullUrl = false;

    /**
     * Breadcrumb constructor.
     * @param null|string $separator
     */
    public function __construct(?string $separator = '>')
    {

        $this->separator     = $separator;
        $this->ulClass       = null;
        $this->ulAriaLabel   = null;
        $this->liClass       = null;
        $this->liActiveClass = 'active';
        $this->liAriaCurrent = 'page';
        $this->linkClass     = '';
        $this->links         = [];

    }

    /**
     * @return string|null
     */
    public function getSeparator(): ?string
    {
        return $this->separator;
    }

    /**
     * @param string|null $separator
     * @return self
     */
    public function setSeparator(?string $separator): self
    {
        $this->separator = $separator;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getUlClass(): ?string
    {
        return $this->ulClass;
    }

    /**
     * @param string|null $ulClass
     * @return self
     */
    public function setUlClass(?string $ulClass): self
    {
        $this->ulClass = $ulClass;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getUlAriaLabel(): ?string
    {
        return $this->ulAriaLabel;
    }

    /**
     * @param string|null $ulAriaLabel
     * @return self
     */
    public function setUlAriaLabel(?string $ulAriaLabel): self
    {
        $this->ulAriaLabel = $ulAriaLabel;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getLiClass(): ?string
    {
        return $this->liClass;
    }

    /**
     * @param string|null $liClass
     * @return self
     */
    public function setLiClass(?string $liClass): self
    {
        $this->liClass = $liClass;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getLiActiveClass(): ?string
    {
        return $this->liActiveClass;
    }

    /**
     * @param string|null $liActiveClass
     * @return self
     */
    public function setLiActiveClass(?string $liActiveClass): self
    {
        $this->liActiveClass = $liActiveClass;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getLiAriaCurrent(): ?string
    {
        return $this->liAriaCurrent;
    }

    /**
     * @param string|null $liAriaCurrent
     * @return self
     */
    public function setLiAriaCurrent(?string $liAriaCurrent): self
    {
        $this->liAriaCurrent = $liAriaCurrent;
        return $this;
    }

    /**
     * @return array
     */
    public function getLinks(): array
    {
        return $this->links;
    }

    /**
     * @param array $links
     * @return self
     */
    public function setLinks(array $links): self
    {
        $this->links = $links;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getLinkClass(): ?string
    {
        return $this->linkClass;
    }

    /**
     * @param string|null $linkClass
     * @return self
     */
    public function setLinkClass(?string $linkClass): self
    {
        $this->linkClass = $linkClass;
        return $this;
    }

    /**
     * @return bool
     */
    public function isFullUrl(): bool
    {
        return $this->fullUrl;
    }

    /**
     * @param bool $fullUrl
     * @return self
     */
    public function setFullUrl(bool $fullUrl): self
    {
        $this->fullUrl = $fullUrl;
        return $this;
    }






    /**
     * @param string $baseUrl
     * @param string $title
     * @param bool $showTitle
     * @param null|string $icon
     * @param string|null $class
     * @return $this
     */
    public function base(
        string $baseUrl,
        string $title,
        bool $showTitle = true,
        ?string $icon   = null,
        string $class   = null
    ): self
    {
        $this->base = [
            "url"       => $baseUrl,
            "title"     => $title,
            "showTitle" => $showTitle,
            "icon"      => $icon,
            "class"     => $class
        ];
        return $this;
    }

    /**
     * @param string $title
     * @param string|null $url
     * @param null|string $class
     * @param bool $isFullUrl
     * @return self
     */
    public function addCrumb(string $title, ?string $url, ?string $class = null,  bool $isFullUrl = false): self
    {
        $this->links[] = $this->parts($title, $url, $class, $isFullUrl);
        return $this;
    }

    /**
     * @return string
     */
    public function render(): string
    {
        $ulAriaLabel = $this->ulAriaLabel;
        $ulClass     = $this->ulClass;
        $init        = "<ul class=\"$ulClass\" aria-label=\"$ulAriaLabel\">";
        $end         = "</ul>";

        return $init . ($this->links ? $this->setBase() : "") . $this->mount($this->links) . $end;
    }

    public function allCrumbs(): ?array
    {
        return (!empty($this->links) ? $this->links : null);
    }

    /**
     * @param array $links
     * @return string
     */
    private function mount(array $links): ?string
    {
        if (!$links) {
            return null;
        }

        $last          = count($links) - 1;
        $breadcrumb    = "";
        $liClass       = $this->liClass;
        $liActiveClass = $this->liActiveClass;
        $liAriaCurrent = $this->liAriaCurrent;
        $linkClass     = $this->linkClass;

        for ($b = 0; $b <= $last; $b++) {

            if ($b == $last) {
                $breadcrumb .= "<li class=\"$liActiveClass {$links[$b]["class"]}\" aria-current=\"$liAriaCurrent\">{$this->separator}{$links[$b]["title"]}</li>" . "\n";
            } else {
                $breadcrumb .= "<li class=\"$liClass {$links[$b]["class"]}\">{$this->separator}<a href=\"{$links[$b]["url"]}\" class=\"$linkClass\">{$links[$b]["title"]}</a></li>" . "\n";
            }
        }

        return $breadcrumb;
    }

    /**
     * @return string
     */
    private function setBase(): string
    {
        $title         = ($this->base["showTitle"] ? $this->base["title"] : null);
        $icon          = $this->base["icon"];
        $class         = $this->base["class"];
        $url           = $this->base["url"];
        $liClass       = $this->liClass;
        $liActiveClass = $this->liActiveClass;
        $liAriaCurrent = $this->liAriaCurrent;
        $linkClass     = $this->linkClass;

        if (!$this->links) {
            return "<li class=\"$liActiveClass {$class}\" aria-current=\"$liAriaCurrent\">{$icon}{$title}</li>";
        }

        return "<li class=\"$liClass {$class}\"><a href=\"{$url}\" class=\"$linkClass\">{$icon}{$title}</a></li>";
    }

    /**
     * @param string $title
     * @param string|null $url
     * @param string|null $class
     * @param bool $isFullUrl
     * @return array
     */
    private function parts(
        string $title,
        string $url     = null,
        string $class   = null,
        bool $isFullUrl = false
    ): array
    {
        $url = $this->setUrl($url, $isFullUrl);

        return [
            "url"   => $url,
            "title" => $title,
            "class" => $class,
        ];
    }

    /**
     * @param string $url
     * @param bool $isFullUrl
     * @return string
     */
    private function setUrl(string $url, bool $isFullUrl = false): string
    {
        if (!$isFullUrl) {
            $url = str_replace($this->base["url"], "", $url);
            $url = ($url[0] == "/" ? $url : "/" . $url);

            return $this->base["url"] . $url;
        }
        return $this->base["url"] = $url;

    }
}
