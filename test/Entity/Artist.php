<?php

namespace ApiSkeletonsTest\Doctrine\ORM\QueryBuilder\Filter\Entity;

/**
 * Artist
 */
class Artist
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var int
     */
    private $id;

    /**
     * @var \Doctrine\Common\Collections\Collection
     */
    private $performances;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->performances = new \Doctrine\Common\Collections\ArrayCollection();
    }

    /**
     * Set name.
     *
     * @param string $name
     *
     * @return Artist
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get name.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Get id.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Add performance.
     *
     * @param \ApiSkeletonsTest\Doctrine\ORM\QueryBuilder\Filter\Entity\Performance $performance
     *
     * @return Artist
     */
    public function addPerformance(\ApiSkeletonsTest\Doctrine\ORM\QueryBuilder\Filter\Entity\Performance $performance)
    {
        $this->performances[] = $performance;

        return $this;
    }

    /**
     * Remove performance.
     *
     * @param \ApiSkeletonsTest\Doctrine\ORM\QueryBuilder\Filter\Entity\Performance $performance
     *
     * @return boolean TRUE if this collection contained the specified element, FALSE otherwise.
     */
    public function removePerformance(\ApiSkeletonsTest\Doctrine\ORM\QueryBuilder\Filter\Entity\Performance $performance)
    {
        return $this->performances->removeElement($performance);
    }

    /**
     * Get performances.
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getPerformances()
    {
        return $this->performances;
    }
}
