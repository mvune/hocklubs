<?php

namespace Mvune\Hocklubs;

class Hocklub
{
    /** @var string $name */
    public $name;

    /** @var string $logo */
    public $logo;

    /** @var string $link */
    public $link;

    /** @var string $phone */
    public $phone;

    /** @var string $email */
    public $email;

    /** @var string $website */
    public $website;

    /** @var string $address */
    public $address;

    /** @var string $outfit */
    public $outfit;

    /** @var string $pitches */
    public $pitches;

    /** @var int $members */
    public $members;

    /** @var string $founded */
    public $founded;

    /**
     * @return array
     */
    public function toArray()
    {
        return json_decode(json_encode($this), true);
    }
}
