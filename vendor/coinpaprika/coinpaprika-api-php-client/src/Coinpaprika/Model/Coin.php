<?php

namespace Coinpaprika\Model;

use Coinpaprika\Model\Traits\IdentityTrait;
use Coinpaprika\Model\Traits\NameTrait;

/**
 * Class Coin
 *
 * @package \Coinpaprika\Model
 *
 * @author Krzysztof Przybyszewski <kprzybyszewski@greywizard.com>
 */
class Coin
{
    use IdentityTrait, NameTrait;

    /**
     * @var string
     */
    private $symbol;

    /**
     * @var int
     */
    private $rank;

    /**
     * @var boolean
     */
    private $new;

    /**
     * @var boolean
     */
    private $active;

    /**
     * @return string
     */
    public function getSymbol(): string
    {
        return $this->symbol;
    }

    /**
     * @param string $symbol
     */
    public function setSymbol(string $symbol): void
    {
        $this->symbol = $symbol;
    }

    /**
     * @return int
     */
    public function getRank(): int
    {
        return $this->rank;
    }

    /**
     * @param int $rank
     */
    public function setRank(int $rank): void
    {
        $this->rank = $rank;
    }

    /**
     * @return bool
     */
    public function isNew(): bool
    {
        return $this->new;
    }

    /**
     * @param bool $new
     */
    public function setNew(bool $new): void
    {
        $this->new = $new;
    }

    /**
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->active;
    }

    /**
     * @param bool $active
     */
    public function setActive(bool $active): void
    {
        $this->active = $active;
    }
}
