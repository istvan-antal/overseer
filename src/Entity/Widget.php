<?php

namespace Entity;

/**
 * @Table()
 * @Entity
 */
class Widget {

    /**
     * @var integer
     *
     * @Column(name="id", type="integer", nullable=false, unique=false)
     * @Id
     * @GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * @var string
     *
     * @Column(type="string", length=45, nullable=false, unique=false)
     */
    private $name;
    
     /**
     * @var string
     *
     * @Column(type="string", length=45, nullable=false, unique=false)
     */
    private $type;
    
    /**
     * @var array
     *
     * @Column(type="json_array")
     */
    private $queryOptions = array();
    
    /**
     * @var array
     *
     * @Column(type="json_array")
     */
    private $displayOptions = array();

    /**
     * @return integer 
     */
    public function getId() {
        return $this->id;
    }

    /**
     * @param string $name
     * @return Widget
     */
    public function setName($name) {
        $this->name = $name;

        return $this;
    }

    /**
     * @return string 
     */
    public function getName() {
        return $this->name;
    }
    
    /**
     * @param string $type
     * @return Widget
     */
    public function setType($type) {
        $this->name = $type;

        return $this;
    }

    /**
     * @return string 
     */
    public function getType() {
        return $this->type;
    }
    
    /**
     * @param array $queryOptions
     * @return Widget
     */
    public function setQueryOptions($queryOptions) {
        $this->queryOptions = $queryOptions;

        return $this;
    }

    /**
     * @return array 
     */
    public function getQueryOptions() {
        return $this->queryOptions;
    }
    
    /**
     * @param array $displayOptions
     * @return Widget
     */
    public function setDisplayOptions($displayOptions) {
        $this->displayOptions = $displayOptions;

        return $this;
    }

    /**
     * @return array 
     */
    public function getDisplayOptions() {
        return $this->displayOptions;
    }
    
    public function __toString() {
        return $this->getName();
    }
}