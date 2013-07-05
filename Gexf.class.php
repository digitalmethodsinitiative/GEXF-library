<?php

# This program is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 3 of the License, or
# (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program.  If not, see <http://www.gnu.org/licenses/>.


/**
 * GEXF library 
 * 
 * @author "Erik Borra" <erik@digitalmethods.net>
 */
define('GEXF_EDGE_DIRECTED', 0);
define('GEXF_EDGE_UNDIRECTED', 1);
define('GEXF_MODE_STATIC', 3);
define('GEXF_MODE_DYNAMIC', 4);
define('GEXF_TIMEFORMAT_DATE', 5);

/**
 * This class accepts nodes and edges, and generates the XML representation
 * 
 */
class Gexf {

    private $title = "";
    private $edgeType = "undirected";
    private $creator = "tools.digitalmethods.net";
    private $mode = "static";
    private $timeformat = false;
    public $nodeObjects = array();
    public $edgeObjects = array();
    private $nodeAttributeObjects = array();
    private $edgeAttributeObjects = array();
    public $gexfFile = "";

    /**
     * 
     * @param int $edgeType either GEXF_EDGE_DIRECTED or GEXF_EDGE_UNDIRECTED
     */
    public function setEdgeType($edgeType) {
        if ($edgeType == GEXF_EDGE_DIRECTED)
            $this->edgeType = 'directed';
        else if ($edgeType == GEXF_EDGE_UNDIRECTED)
            $this->edgeType = 'undirected';
        else
            throw new Exception("Unsupported edge type: $edgeType");
    }

    public function setTitle($title) {
        $this->title = str_replace("&", "&amp;", str_replace("'", "&quot;", str_replace('"', "'", strip_tags(trim($title)))));
    }

    public function setCreator($creator) {
        $this->creator = str_replace("&", "&amp;", str_replace("'", "&quot;", str_replace('"', "'", strip_tags(trim($creator)))));
    }

    public function setMode($mode) {
        if ($mode == GEXF_MODE_STATIC)
            $this->mode = 'static';
        else if ($mode == GEXF_MODE_DYNAMIC)
            $this->mode = 'dynamic';
        else
            throw new Exception("Unsupported mode: $mode");
    }

    public function setTimeFormat($format) {
        if ($format == GEXF_TIMEFORMAT_DATE)
            $this->timeformat = 'date';
        else
            throw new Exception("Unsupported time format: $format");
    }

    public function addNode($node) {
        if (!$this->nodeExists($node))
            $this->nodeObjects[$node->id] = $node;
        //else throw new Exception("Node ".$node->id." already exists");
        return $node->id;
    }

    public function nodeExists($node) {
        return array_key_exists($node->id, $this->nodeObjects);
    }

    /**
     * Add child node
     * 
     * @todo this belongs in GexfNode, not here
     * 
     * @param GexfNode $child
     * @param GexfNode $parent
     * @return string 
     */
    public function addNodeChild($child, $parent) {
        if (!$this->childExists($child, $parent))
            $this->nodeObjects[$parent->id]->addNodeChild($child);
        //else throw new Exception("Child node ".$node->id." already exists");
        return $child->id;
    }

    public function childExists($node, $child) {
        return array_key_exists($child->id, $node->children);
    }

    public function addEdge($source, $target, $weight = 1) {
        $edge = new GexfEdge($source, $target, $weight, $this->edgeType);
        // if edge did not exist, add to list
        if (array_key_exists($edge->id, $this->edgeObjects) == false)
            $this->edgeObjects[$edge->id] = $edge;
        // else add weight to existing edge
        else
            $this->edgeObjects[$edge->id]->addToEdgeWeight($weight);
        return $edge->id;
    }

    public function addEdgeSpell($eid, $start, $end) {
        if (array_key_exists($eid, $this->edgeObjects) == false)
            die('make an edge before you add a spell');
        $this->edgeObjects[$eid]->addEdgeSpell($start, $end);
    }

    // @todo, go through gexf primer to include all options	
    public function render() {
        $nodes = $this->renderNodes($this->nodeObjects);
        $edges = $this->renderEdges($this->edgeObjects);
        $nodeAttributes = $this->renderNodeAttributes();
        $edgeAttributes = $this->renderEdgeAttributes();

        $this->gexfFile = '<?xml version="1.0" encoding="UTF-8"?>
		<gexf xmlns="http://www.gexf.net/1.2draft"
			xmlns:xsi="http://wwww.w3.org/2001/XMLSchema-instance"
			xsi:schemaLocation="http://www.gexf.net/1.2draft 
			http://www.gexf.net/1.2draft/gexf.xds"
			xmlns:viz="http://www.gexf.net/1.2draft/viz"
			version="1.2">
			<meta>
				<creator>' . $this->creator . '</creator>
				<description>' . $this->title . '</description>
			</meta>
			<graph defaultedgetype="' . $this->edgeType . '" mode="' . $this->mode . '"' . (!empty($this->timeformat) ? ' timeformat="' . $this->timeformat . '"' : '') . '>
				' . $nodeAttributes . '
				' . $edgeAttributes . '
				' . $nodes . '
				' . $edges . '
			</graph>
		</gexf>';
    }

    public function renderNodes($nodeObjects) {
        $xmlNodes = "<nodes>\n";

        foreach ($nodeObjects as $id => $node) {

            $xmlNodes .= '<node id="' . $node->id . '" label="' . $node->name . '">' . "\n";

            // add color
            if ($node->color != array())
                $xmlNodes .= '<viz:color r="' . $node->color['r'] . '" g="' . $node->color['g'] . '" b="' . $node->color['b'] . '" a="' . $node->color['a'] . '"/>';

            // add attributes
            if (count($node->attributes)) {
                foreach ($node->attributes as $attribute) {
                    $xmlNodes .= '<attvalue for="' . $attribute->id . '" value="' . $attribute->value . '"/>' . "\n";

                    if (array_key_exists($attribute->id, $this->nodeAttributeObjects) === false)
                        $this->nodeAttributeObjects[$attribute->id] = $attribute;
                }
            }

            // add spells (the times this node lives)
            if (count($node->spells)) {
                $xmlNodes .= "<spells>\n";
                foreach ($node->spells as $spell) {
                    $xmlNodes .= '<spell' . (isset($spell->startdate) ? ' start="' . $spell->startdate . '"' : '') . (isset($spell->enddate) ? ' end="' . $spell->enddate . '"' : '') . " />\n";
                }
                $xmlNodes .= "</spells>\n";
            }

            // add children
            if (count($node->children)) {
                $xmlNodes .= $this->renderNodes($node->children);
            }

            $xmlNodes .= "</node>\n";
        }
        $xmlNodes .= "</nodes>\n";

        return $xmlNodes;
    }

    public function renderEdges($edgeObjects) {
        $xmlEdges = "<edges>\n";
        foreach ($edgeObjects as $edge) {

            $xmlEdges .= '<edge id="' . $edge->id . '" source="' . $edge->source . '" target="' . $edge->target . '" weight="' . $edge->weight . '">' . "\n";

            // add attributes
            if (count($edge->attributes)) {
                foreach ($edge->attributes as $attribute) {
                    $xmlEdges .= '<attvalue for="' . $attribute->id . '" value="' . $attribute->value . '"/>' . "\n";

                    if (array_key_exists($attribute->id, $this->edgeAttributeObjects) === false)
                        $this->edgeAttributeObjects[$attribute->id] = $attribute;
                }
            }

            // add spells (the times this edge lives)
            if (count($edge->spells)) {
                $xmlEdges .= "<spells>\n";
                foreach ($edge->spells as $spell) {
                    $xmlEdges .= '<spell' . (isset($spell->startdate) ? ' start="' . $spell->startdate . '"' : '') . (isset($spell->enddate) ? ' end="' . $spell->enddate . '"' : '') . " />\n";
                }
                $xmlEdges .= "</spells>\n";
            }

            $xmlEdges .= "</edge>\n";
        }
        $xmlEdges .= "</edges>\n";

        return $xmlEdges;
    }

    public function renderNodeAttributes() {
        $xmlNodeAttributes = "";
        if (count($this->nodeAttributeObjects)) {
            $xmlNodeAttributes = '<attributes class="node">';
            foreach ($this->nodeAttributeObjects as $attribute) {
                $xmlNodeAttributes .= '<attribute id="' . $attribute->id . '" title="' . $attribute->name . '" type="' . $attribute->type . '"/>' . "\n";
                // @ todo add time attribute
            }
            $xmlNodeAttributes .= "</attributes>\n";
        }
        return $xmlNodeAttributes;
    }

    public function renderEdgeAttributes() {
        $xmlEdgeAttributes = "";
        if (count($this->edgeAttributeObjects)) {
            $xmlEdgeAttributes .= '<attributes class="edge">';
            foreach ($this->edgeAttributeObjects as $attribute) {
                $xmlEdgeAttributes .= '<attribute id="' . $attribute->id . '" title="' . $attribute->name . '" type="' . $attribute->type . '"/>' . "\n";
                // @ todo add time attribute
            }
            $xmlEdgeAttributes .= "</attributes>\n";
        }
        return $xmlEdgeAttributes;
    }

}

class GexfNode {

    public $id = "";
    public $name = "";
    public $attributes = array();
    public $spells = array();
    public $children = array();
    public $color = array();

    public function __construct($name, $idprefix = null) {
        $this->setNodeName($name);
        $this->setNodeId($idprefix);
    }

    public function getNodeId() {
        return $this->id;
    }

    public function setNodeId($idprefix = null) {
        if (isset($idprefix))
            $this->id = $idprefix . md5($this->name);
        else
            $this->id = "n-" . md5($this->name);
    }

    public function getNodeName() {
        return $this->name;
    }

    public function setNodeName($name) {
        $this->name = str_replace("&", "&amp;", str_replace("'", "&quot;", str_replace('"', "'", strip_tags(trim($name)))));
    }

    public function getNodeAttributes() {
        return $this->attributes;
    }

    public function addNodeAttribute($name, $value, $type = "string") {
        $attribute = new GexfAttribute($name, $value, $type);
        $this->attributes[$attribute->id] = $attribute;
    }

    public function getNodeAttributeValue($attributeName) {
        $attribute = new GexfAttribute($attributeName, "");
        $aid = $attribute->getAttributeId();
        if (isset($this->attributes[$aid]))
            return $this->attributes[$aid]->getAttributeValue();
        else
            return false;
    }

    public function getNodeColor() {
        return $this->color;
    }

    public function setNodeColor($r = 255, $g = 255, $b = 255, $a = 1) {
        $this->color = array("r" => $r, "g" => $g, "b" => $b, "a" => $a);
    }

    public function getNodeSpells() {
        return $this->spells;
    }

    public function addNodeSpell($start, $end) {
        $spell = new GexfSpell($start, $end);
        $this->spells[$spell->getSpellId()] = $spell;
    }

    public function getNodeChildren() {
        return $this->children;
    }

    public function addNodeChild($node) {
        // @todo throw Exception if duplicate node
        $this->children[$node->id] = $node;
    }

}

class GexfEdge {

    public $id = "";
    public $source = "";
    public $target = "";
    public $weight = 1;
    public $attributes = array();
    public $spells = array();
    public $edgeType = "undirected";

    public function __construct($source, $target, $weight, $edgeType) {
        $this->setEdgeSource($source);
        $this->setEdgeTarget($target);
        $this->setEdgeWeight($weight);
        $this->setEdgeType($edgeType);
        $this->setEdgeId();
    }

    public function setEdgeType($edgeType) {
        $this->edgeType = $edgeType;
    }

    public function getEdgeType() {
        return $this->edgeType;
    }

    public function getEdgeSource() {
        return $this->source;
    }

    public function setEdgeSource($source) {
        $this->source = $source->id;
    }

    public function getEdgeTarget() {
        return $this->target;
    }

    public function setEdgeTarget($target) {
        $this->target = $target->id;
    }

    public function getEdgeWeight() {
        return $this->weight;
    }

    public function setEdgeWeight($weight) {
        if (!is_int($weight))
            return array('error' => 'weight != int'); //@todo is this the right way to raise errors?
        $this->weight = $weight;
    }

    public function addToEdgeWeight($weight) {
        if (!is_int($weight))
            return array('error' => 'weight != int'); //@todo is this the right way to raise errors?
        $this->weight += $weight;
    }

    public function getEdgeId() {
        return $this->id;
    }

    public function setEdgeId() {
        $sort = array($this->source, $this->target);
        if ($this->edgeType == "undirected")   // if undirected all concatenations need to be result in same id
            sort($sort);
        $this->id = "e-" . implode("", $sort);
    }

    public function getEdgeAttributes() {
        return $this->attributes;
    }

    public function addEdgeAttribute($name, $value, $type = "string") {
        $attribute = new GexfAttribute($name, $value, $type);
        $this->attributes[$attribute->id] = $attribute;
    }

    public function getEdgeSpells() {
        return $this->spells;
    }

    public function addEdgeSpell($start, $end) {
        $spell = new GexfSpell($start, $end);
        $this->spells[$spell->getSpellId()] = $spell;
    }

}

class GexfAttribute {

    public $id = "";
    public $name = "";
    public $value = "";
    public $type = "";

    public function __construct($name, $value, $type = "string") {
        $this->setAttributeName($name);
        $this->setAttributeId($this->name);
        $this->setAttributeValue($value);
        $this->setAttributeType($type);
    }

    public function getAttributeName() {
        return $this->name;
    }

    public function setAttributeName($name) {
        $this->name = str_replace("&", "&amp;", str_replace("'", "&quot;", str_replace('"', "'", strip_tags(trim($name)))));
    }

    public function getAttributeId() {
        return $this->id;
    }

    public function setAttributeId() {
        $this->id = "a-" . md5($this->name);
    }

    public function getAttributeValue() {
        return $this->value;
    }

    public function setAttributeValue($value) {
        $this->value = str_replace("&", "&amp;", str_replace("'", "&quot;", str_replace('"', "'", strip_tags(trim($value)))));
    }

    public function getAttributeType($type) {
        return $this->type;
    }

    public function setAttributeType($type) {
        $this->type = str_replace("&", "&amp;", str_replace("'", "&quot;", str_replace('"', "'", strip_tags(trim($type)))));
    }

}

// used for adding time to nodes and edges (attributes are currently unsupported)
class GexfSpell {

    public $id;
    public $startdate;
    public $enddate;

    public function __construct($start, $end) {
        $this->startdate = $this->checkFormat($start);
        $this->enddate = $this->checkFormat($end);
        $this->setSpellId();
    }

    public function checkFormat($date) {
        if (!preg_match("/\d{4}-\d{2}-\d{2}/", $date))
            throw Exception("Time not in right format");
        return $date;
    }

    public function setSpellId() {
        $this->id = $this->startdate . "-" . $this->enddate;
    }

    public function getSpellId() {
        return $this->id;
    }

}

?>