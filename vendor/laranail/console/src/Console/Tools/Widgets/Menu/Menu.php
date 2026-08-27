<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Widgets\Menu;

use Closure;

use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

use Simtabi\Laranail\Console\Tools\Formatting\ConsoleUIFormatter;
use Simtabi\Laranail\Console\Tools\Support\Capabilities;
use Simtabi\Laranail\Console\Tools\Support\Color;
use Simtabi\Laranail\Console\Tools\Support\Config;
use Simtabi\Laranail\Console\Tools\Support\Keypress;
use Simtabi\Laranail\Console\Tools\Support\Lang;
use Simtabi\Laranail\Console\Tools\Widgets\Box;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * A native interactive menu.
 *
 * On a TTY with raw key input it renders a navigable frame (↑/↓ to move, space to
 * toggle checkboxes/radios, enter to choose, q/esc to exit). Where raw input
 * isn't available (non-TTY, Windows) it transparently falls back to
 * laravel/prompts — so it always works and needs no ext-posix.
 *
 * A fluent `->menu($title, $options)->open()` builder with `addOption()` /
 * `addQuestion()` and a `Command::macro('menu')` helper.
 */
final class Menu
{
    /** @var list<Item> */
    private array $items = [];

    private string $foreground = '';

    private ?int $width = null;

    private int $padding = 1;

    private ?string $exitText = null;

    private readonly Capabilities $capabilities;

    private readonly OutputInterface $output;

    /**
     * @param array<int|string, string>|list<string> $options
     */
    public function __construct(
        private string $title = '',
        array $options = [],
        ?Capabilities $capabilities = null,
        ?OutputInterface $output = null,
    ) {
        $this->title = ConsoleUIFormatter::sanitizeText($title);
        $this->capabilities = $capabilities ?? Capabilities::detect();
        $this->output = $output ?? new ConsoleOutput;

        $fg = Config::get('menu.foreground');
        $this->foreground = is_string($fg) ? $fg : '';
        $width = Config::get('menu.width');
        $this->width = is_numeric($width) ? (int) $width : null;

        if ($options !== []) {
            $this->addOptions($options);
        }
    }

    /**
     * @param array<int|string, string>|list<string> $options
     */
    public static function make(string $title = '', array $options = []): self
    {
        return new self($title, $options);
    }

    public function addOption(mixed $value, string $label): self
    {
        $this->items[] = new MenuItem($value, ConsoleUIFormatter::sanitizeText($label));

        return $this;
    }

    /**
     * Bulk add options. A list adds label==value entries; an associative array is
     * treated as value => label.
     *
     * @param array<int|string, string>|list<string> $options
     */
    public function addOptions(array $options): self
    {
        $isList = array_is_list($options);

        foreach ($options as $value => $label) {
            $this->addOption($isList ? $label : $value, $label);
        }

        return $this;
    }

    public function addQuestion(string $label, string $placeholder = ''): self
    {
        $this->items[] = new QuestionItem(ConsoleUIFormatter::sanitizeText($label), $placeholder);

        return $this;
    }

    public function addStaticItem(string $label = ''): self
    {
        $this->items[] = new StaticItem(ConsoleUIFormatter::sanitizeText($label));

        return $this;
    }

    public function addLineBreak(): self
    {
        $this->items[] = new StaticItem;

        return $this;
    }

    /**
     * @param Closure(self): void $build
     */
    public function addSubMenu(string $label, Closure $build): self
    {
        $child = new self($label, [], $this->capabilities, $this->output);
        $build($child);
        $this->items[] = new SubMenuItem(ConsoleUIFormatter::sanitizeText($label), $child);

        return $this;
    }

    public function addCheckbox(mixed $value, string $label, bool $checked = false): self
    {
        $this->items[] = new CheckboxItem($value, ConsoleUIFormatter::sanitizeText($label), $checked);

        return $this;
    }

    public function addRadio(mixed $value, string $label, bool $checked = false, string $group = 'default'): self
    {
        $this->items[] = new RadioItem($value, ConsoleUIFormatter::sanitizeText($label), $checked, $group);

        return $this;
    }

    public function setWidth(int $width): self
    {
        $this->width = $width;

        return $this;
    }

    public function setPadding(int $padding): self
    {
        $this->padding = max($padding, 0);

        return $this;
    }

    public function setForegroundColour(string $colour): self
    {
        $this->foreground = $colour;

        return $this;
    }

    public function setExitButtonText(string $text): self
    {
        $this->exitText = ConsoleUIFormatter::sanitizeText($text);

        return $this;
    }

    /**
     * @return list<Item>
     */
    public function items(): array
    {
        return $this->items;
    }

    /**
     * Open the menu and return the chosen value: a single value (select), the
     * typed answer (question), a nested result (submenu), an array of values
     * (checkboxes), or null if exited.
     */
    public function open(): mixed
    {
        if ($this->selectableIndexes() === []) {
            return null;
        }

        if ($this->capabilities->isInteractive() && Keypress::isSupported()) {
            return $this->loop();
        }

        return $this->fallback();
    }

    /**
     * Render the menu frame with the cursor on the given item index.
     */
    public function renderFrame(int $cursor): string
    {
        $lines = [];

        foreach ($this->items as $index => $item) {
            $pointer = $index === $cursor && $item->selectable() ? '> ' : '  ';
            $lines[] = $pointer . $this->mark($item) . $item->label();
        }

        $lines[] = '';
        $lines[] = '  [' . ($this->exitText ?? Lang::get('widgets.menu.exit', 'Exit')) . ']';

        $box = Box::make($lines)->title($this->title)->padding($this->padding);

        if ($this->width !== null) {
            $box->width($this->width);
        }

        $rendered = $box->render();

        return $this->foreground !== '' ? Color::make()->fg($rendered, $this->foreground) : $rendered;
    }

    private function mark(Item $item): string
    {
        return match (true) {
            $item instanceof CheckboxItem => $item->checked ? '[x] ' : '[ ] ',
            $item instanceof RadioItem => $item->checked ? '(o) ' : '( ) ',
            default => '',
        };
    }

    /**
     * The interactive key-driven loop (real TTY only).
     */
    private function loop(): mixed
    {
        $keys = Keypress::make();
        $indexes = $this->selectableIndexes();
        $pos = 0;
        $hasToggles = $this->hasToggleItems();

        while (true) {
            $this->output->write("\e[2J\e[H", false, OutputInterface::OUTPUT_RAW);
            $this->output->writeln($this->renderFrame($indexes[$pos]));

            $key = $keys->listen();

            switch ($key) {
                case Keypress::KEY_UP:
                    $pos = ($pos - 1 + count($indexes)) % count($indexes);
                    break;
                case Keypress::KEY_DOWN:
                    $pos = ($pos + 1) % count($indexes);
                    break;
                case Keypress::KEY_SPACE:
                    $this->toggle($this->items[$indexes[$pos]]);
                    break;
                case Keypress::KEY_ESC:
                case 'q':
                case Keypress::KEY_CTRL_C:
                    return $hasToggles ? $this->checkedValues() : null;
                case Keypress::KEY_ENTER:
                    if ($hasToggles) {
                        return $this->checkedValues();
                    }

                    return $this->resolve($this->items[$indexes[$pos]]);
            }
        }
    }

    /**
     * Non-interactive selection via laravel/prompts.
     */
    private function fallback(): mixed
    {
        if ($this->hasToggleItems()) {
            return $this->fallbackMultiselect();
        }

        $options = [];
        foreach ($this->selectableIndexes() as $index) {
            $options[(string) $index] = $this->items[$index]->label();
        }

        $choice = select($this->title !== '' ? $this->title : Lang::get('widgets.menu.select', 'Select'), $options);

        return $this->resolve($this->items[(int) $choice]);
    }

    /**
     * @return list<mixed>
     */
    private function fallbackMultiselect(): array
    {
        $options = [];
        $defaults = [];

        foreach ($this->items as $index => $item) {
            if ($item instanceof CheckboxItem || $item instanceof RadioItem) {
                $options[(string) $index] = $item->label();

                if ($item->checked) {
                    $defaults[] = (string) $index;
                }
            }
        }

        /** @var list<int|string> $chosen */
        $chosen = multiselect($this->title !== '' ? $this->title : Lang::get('widgets.menu.select', 'Select'), $options, $defaults);

        $values = [];
        foreach ($chosen as $index) {
            $item = $this->items[(int) $index];
            if ($item instanceof CheckboxItem || $item instanceof RadioItem) {
                $values[] = $item->value;
            }
        }

        return $values;
    }

    private function resolve(Item $item): mixed
    {
        return match (true) {
            $item instanceof SubMenuItem => $item->submenu->open(),
            $item instanceof QuestionItem => text($item->label(), $item->placeholder),
            $item instanceof MenuItem => $this->fire($item),
            $item instanceof CheckboxItem, $item instanceof RadioItem => $item->value,
            default => null,
        };
    }

    private function fire(MenuItem $item): mixed
    {
        if ($item->callback instanceof Closure) {
            ($item->callback)($item->value);
        }

        return $item->value;
    }

    private function toggle(Item $item): void
    {
        if ($item instanceof CheckboxItem) {
            $item->toggle();

            return;
        }

        if ($item instanceof RadioItem) {
            foreach ($this->items as $other) {
                if ($other instanceof RadioItem && $other->group === $item->group) {
                    $other->checked = false;
                }
            }

            $item->checked = true;
        }
    }

    /**
     * @return list<mixed>
     */
    private function checkedValues(): array
    {
        $values = [];

        foreach ($this->items as $item) {
            if (($item instanceof CheckboxItem || $item instanceof RadioItem) && $item->checked) {
                $values[] = $item->value;
            }
        }

        return $values;
    }

    private function hasToggleItems(): bool
    {
        return array_any($this->items, fn (Item $item): bool => $item instanceof CheckboxItem || $item instanceof RadioItem);
    }

    /**
     * @return list<int>
     */
    private function selectableIndexes(): array
    {
        $indexes = [];

        foreach ($this->items as $index => $item) {
            if ($item->selectable()) {
                $indexes[] = $index;
            }
        }

        return $indexes;
    }
}
