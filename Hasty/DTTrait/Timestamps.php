<?php

namespace Hasty\DTTrait;

use DateTime;

trait Timestamps
{
    /**
     * @Column(name="created_at", type="datetime", nullable=false)
     * @var \DateTime
     */
    private $createdAt;
    /**
     * @Column(name="updated_at", type="datetime", nullable=false)
     * @var \DateTime
     */
    private $updatedAt;

    /**
     * @PrePersist
     */
    public function prePersist()
    {
        $now = new Datetime;
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    /**
     * @PreUpdate
     */
    public function preUpdate()
    {
        $this->updatedAt = new DateTime;
    }

    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTime $createdAt)
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(DateTime $updatedAt)
    {
        $this->updatedAt = $updatedAt;
    }
}
