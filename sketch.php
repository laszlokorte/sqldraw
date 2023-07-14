<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

function make_path($smoothness, $points = []) {
	$stringPath = [];
	$stringPath[] = ('M' . $points[0]->x . ' ' . $points[0]->y);


    for ($i = 0; $i < count($points) - 1; $i += 1)
    {
        $p0x = ($i > 0) ? $points[$i - 1]->x : $points[0]->x;
        $p0y = ($i > 0) ? $points[$i - 1]->y : $points[0]->y;
        $p1x = $points[$i]->x;
        $p1y = $points[$i]->y;
        $p2x = $points[$i + 1]->x;
        $p2y = $points[$i + 1]->y;
        $p3x = ($i != count($points) - 2) ? $points[$i + 2]->x : $p2x;
        $p3y = ($i != count($points) - 2) ? $points[$i + 2]->y : $p2y;

        $cp1x = $p1x + ($p2x - $p0x) / 6 * $smoothness;
        $cp1y = $p1y + ($p2y - $p0y) / 6 * $smoothness;

        $cp2x = $p2x - ($p3x - $p1x) / 6 * $smoothness;
        $cp2y = $p2y - ($p3y - $p1y) / 6 * $smoothness;

        $cx = 0.125 * $p1x + 0.75 * 0.5 * $cp1x + 1.5 * 0.25 * $cp2x + 0.125 * $p2x;
        $cy = 0.125 * $p1y + 0.75 * 0.5 * $cp1y + 1.5 * 0.25 * $cp2y + 0.125 * $p2y;


        $stringPath[] = ("C". round($cp1x, 2) .' '. round($cp1y, 2) .' '. round($cp2x, 2) .' '. round($cp2y, 2) .' '. round($p2x, 2) .' '. round($p2y, 2));
    }

	return implode(" ", $stringPath);
}

$dbh = new PDO('sqlite::memory:');
$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$dbh->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$dbh->exec(<<<SQL
	PRAGMA foreign_keys = ON;

	CREATE TABLE node_type (
		id INTEGER PRIMARY KEY AUTOINCREMENT,
		name TEXT,
		decorator_path TEXT,
		layer_level INTEGER DEFAULT 0
	);

	CREATE TABLE node_type_anchor (
		id INTEGER PRIMARY KEY AUTOINCREMENT,
		node_type_id INTEGER,
		x_percent INTEGER,
		y_percent INTEGER,
		x_offset INTEGER,
		y_offset INTEGER,
		width_percent INTEGER,
		height_percent INTEGER,
		width_const INTEGER,
		height_const INTEGER,

		CONSTRAINT fk__node_type_anchor__node_type_id FOREIGN KEY (node_type_id) REFERENCES node_type(id)
	);

	CREATE TABLE node_type_region (
		id INTEGER PRIMARY KEY AUTOINCREMENT,
		node_type_id INTEGER,
		x_percent INTEGER,
		y_percent INTEGER,
		x_offset INTEGER,
		y_offset INTEGER,
		width_percent INTEGER,
		height_percent INTEGER,
		width_const INTEGER,
		height_const INTEGER,
		decorator_path TEXT,

		CONSTRAINT fk__node_type_region__node_type_id FOREIGN KEY (node_type_id) REFERENCES node_type(id)
	);

	CREATE TABLE node_type_attribute (
		id INTEGER PRIMARY KEY AUTOINCREMENT,
		node_type_id INTEGER,
		name TEXT,
		type TEXT,
		default_value TEXT,

		CONSTRAINT fk__node_type_attribute__node_type_id FOREIGN KEY (node_type_id) REFERENCES node_type(id),
		CONSTRAINT node_type_attribute_id_type_id UNIQUE (id, node_type_id)
	);

	CREATE TABLE node (
		id INTEGER PRIMARY KEY AUTOINCREMENT,
		node_type_id INTEGER,
		x0 INTEGER,
		y0 INTEGER,
		x1 INTEGER,
		y1 INTEGER,
		layer_level INTEGER DEFAULT 0,
		CONSTRAINT fk__node__node_type_id FOREIGN KEY (node_type_id) REFERENCES node_type(id)

		CONSTRAINT node_id_type_id UNIQUE (id, node_type_id)
	);

	CREATE TABLE node_type_attribute_value (
		id INTEGER PRIMARY KEY AUTOINCREMENT,
		node_id INTEGER,
		node_type_id INTEGER,
		node_type_attribute_id INTEGER,
		value TEXT,

		CONSTRAINT fk__node_type_attribute__node_type_id FOREIGN KEY (node_type_id) REFERENCES node_type(id),
		CONSTRAINT fk__node_type_attribute__node_type_attribute_id FOREIGN KEY (node_type_attribute_id, node_type_id) REFERENCES node_type_attribute(id, node_type_id),
		CONSTRAINT fk__node_type_attribute__node_id FOREIGN KEY (node_id, node_type_id) REFERENCES node(id, node_type_id)
	);

	CREATE TABLE node_attribute (
		id INTEGER PRIMARY KEY AUTOINCREMENT,
		name TEXT,
		default_value TEXT,
		type TEXT
	);

	CREATE TABLE node_attribute_value (
		id INTEGER PRIMARY KEY AUTOINCREMENT,
		node_id INTEGER,
		node_attribute_id INTEGER,
		value TEXT,

		CONSTRAINT fk__node_attribute_value__node_id FOREIGN KEY (node_id) REFERENCES node(id),
		CONSTRAINT fk__node_attribute_value__node_attribute_id FOREIGN KEY (node_attribute_id) REFERENCES node_attribute(id)
	);


	CREATE TABLE edge_type (
		id INTEGER PRIMARY KEY AUTOINCREMENT,
		name TEXT,
		layer_level INTEGER DEFAULT 0
	);

	CREATE TABLE edge_type_attribute (
		id INTEGER PRIMARY KEY AUTOINCREMENT,
		edge_type_id INTEGER,
		name TEXT,
		type TEXT,
		default_value TEXT,

		CONSTRAINT fk__edge__edge_type_id FOREIGN KEY (edge_type_id) REFERENCES edge_type(id)
	);

	CREATE TABLE edge_attribute (
		id INTEGER PRIMARY KEY AUTOINCREMENT,
		name TEXT,
		default_value TEXT,
		type TEXT
	);


	CREATE TABLE edge (
		id INTEGER PRIMARY KEY AUTOINCREMENT,
		edge_type_id INTEGER,
		source_node_id INTEGER,
		source_node_type_id INTEGER,
		source_node_type_anchor_id INTEGER,
		target_node_id INTEGER,
		target_node_type_id INTEGER,
		target_node_type_anchor_id INTEGER,

		CONSTRAINT edge_id_type_id UNIQUE (id, edge_type_id)
	);

	CREATE TABLE edge_attribute_value (
		id INTEGER PRIMARY KEY AUTOINCREMENT,
		edge_id INTEGER,
		edge_attribute_id INTEGER,
		value TEXT,

		CONSTRAINT fk__edge_attribute_value__edge_id FOREIGN KEY (edge_id) REFERENCES edge(id),
		CONSTRAINT fk__edge_attribute_value__edge_attribute_id FOREIGN KEY (edge_attribute_id) REFERENCES edge_attribute(id)
	);

	CREATE TABLE edge_type_attribute_value (
		id INTEGER PRIMARY KEY AUTOINCREMENT,
		edge_id INTEGER,
		edge_type_id INTEGER,
		edge_type_attribute_id INTEGER,
		value TEXT,

		CONSTRAINT fk__edge__edge_type_id FOREIGN KEY (edge_type_id) REFERENCES edge_type(id),
		CONSTRAINT fk__edge_type_attribute__edge_type_attribute_id FOREIGN KEY (edge_type_attribute_id, edge_type_id) REFERENCES edge_type_attribute(id, edge_type_id),
		CONSTRAINT fk__edge_type_attribute__edge_id FOREIGN KEY (edge_id, edge_type_id) REFERENCES edge(id, edge_type_id)
	);

	CREATE TABLE edge_waypoint (
		id INTEGER PRIMARY KEY AUTOINCREMENT,
		edge_id INTEGER,
		sort INTEGER,
		x INTEGER,
		y INTEGER,

		CONSTRAINT fk__edge_attribute_value__edge_id FOREIGN KEY (edge_id) REFERENCES edge(id),
		CONSTRAINT edge_waypoint_order UNIQUE (edge_id, sort)
	);

	CREATE TABLE edge_type_anchor_contraint(
		id INTEGER PRIMARY KEY AUTOINCREMENT,
		edge_type_id INTEGER,
		source_anchor_id INTEGER,
		target_anchor_id INTEGER,
		source_limit INTEGER,
		target_limit INTEGER,

		CONSTRAINT fk__edge_type_anchor_contraint__edge_type_id FOREIGN KEY (edge_type_id) REFERENCES edge_type(id),
		CONSTRAINT fk__edge_type_anchor_contraint__source_anchor_id FOREIGN KEY (source_anchor_id) REFERENCES node_type_anchor(id),
		CONSTRAINT fk__edge_type_anchor_contraint__target_anchor_id FOREIGN KEY (target_anchor_id) REFERENCES node_type_anchor(id)
	);

	CREATE VIEW view_shape AS SELECT 
	node.id AS node_id,
	x0,y0,x1,y1,
	(x0 + x1)/2 as cx,
	(y0 + y1)/2 as cy,
	MIN(x0, x1) AS minx,
	MAX(x0, x1) AS maxx,
	MIN(y0, y1) AS miny,
	MAX(y0, y1) AS maxy,
	MAX(y0, y1) - MIN(y0, y1) as height,
	MAX(x0, x1) - MIN(x0, x1) as width,
	node_type.name AS type,
	node_type.decorator_path AS decorator_path,
	(SELECT json_group_object(node_attribute.name, COALESCE(node_attribute_value.value, node_attribute.default_value)) FILTER (where node_attribute.id is not null) FROM node_attribute
	LEFT JOIN node_attribute_value
	ON node_attribute_value.node_id = node.id AND node_attribute.id = node_attribute_value.node_attribute_id) as own_attrs, 
	(SELECT json_group_object(node_type_attribute.name, COALESCE(node_type_attribute_value.value, node_type_attribute.default_value)) FILTER (where node_type_attribute.id is not null) FROM node_type_attribute
	LEFT JOIN node_type_attribute_value
	ON node_type_attribute_value.node_id = node.id 
	AND node_type_attribute.id = node_type_attribute_value.node_type_attribute_id WHERE node_type_attribute.node_type_id = node_type.id) as type_attrs, 
	json_group_array(DISTINCT json_object(
		'anchor_id', node_type_anchor.id,
		'cx', (x0*node_type_anchor.x_percent+x1*(1-node_type_anchor.x_percent)) + node_type_anchor.x_offset,
		'cy', (y0*node_type_anchor.y_percent+y1*(1-node_type_anchor.y_percent)) + node_type_anchor.y_offset,
		'width', ABS(x1 - x0) * node_type_anchor.width_percent + node_type_anchor.width_const,
		'height', ABS(y1 - y0) * node_type_anchor.height_percent + node_type_anchor.height_const
	)) FILTER (where node_type_anchor.id is not null) as anchors,
	json_group_array(DISTINCT json_object(
		'region_id', node_type_region.id,
		'cx', (x0*node_type_region.x_percent+x1*(1-node_type_region.x_percent)) + node_type_region.x_offset,
		'cy', (y0*node_type_region.y_percent+y1*(1-node_type_region.y_percent)) + node_type_region.y_offset,
		'width', ABS(x1 - x0) * node_type_region.width_percent + node_type_region.width_const,
		'height', ABS(y1 - y0) * node_type_region.height_percent + node_type_region.height_const,
		'decorator_path', node_type_region.decorator_path
	)) FILTER (where node_type_region.id is not null) as regions 
	FROM node 
	LEFT JOIN node_type
	ON node_type.id = node.node_type_id

	LEFT JOIN node_type_anchor
	ON node_type_anchor.node_type_id = node.node_type_id
	LEFT JOIN node_type_region
	ON node_type_region.node_type_id = node.node_type_id


	GROUP BY node.id
	ORDER BY node_type.layer_level ASC;



	CREATE VIEW view_edges AS 
	SELECT *, 
	json_group_array(json_object('x', wps.x, 'y', wps.y,'id', wps.id)) FILTER(WHERE wps.id IS NOT NULL) AS waypoints,
	json_object(
		"minx", MIN(source_x, COALESCE(MIN(wps.x), source_x), target_x) - 2, 
		"miny", MIN(source_y, COALESCE(MIN(wps.y), source_y), target_y) - 2, 
		"maxx", max(source_x, COALESCE(max(wps.x), source_x), target_x) + 2, 
		"maxy", max(source_y, COALESCE(max(wps.y), source_y), target_y) + 2
	) AS bounding
	FROM (SELECT 
	edge.id as edge_id, 
	(source.x0*source_anchor.x_percent + source.x1*(1-source_anchor.x_percent)) + source_anchor.x_offset AS source_x,
	(source.y0*source_anchor.y_percent + source.y1*(1-source_anchor.y_percent)) + source_anchor.y_offset AS source_y, 
	(target.x0*target_anchor.x_percent + target.x1*(1-target_anchor.x_percent)) + target_anchor.x_offset AS target_x,
	(target.y0*target_anchor.y_percent + target.y1*(1-target_anchor.y_percent)) + target_anchor.y_offset AS target_y,
	(SELECT json_group_object(edge_attribute.name, COALESCE(edge_attribute_value.value, edge_attribute.default_value)) FILTER (where edge_attribute.id is not null)  FROM edge_attribute
	LEFT JOIN edge_attribute_value
	ON edge_attribute_value.edge_id = edge.id AND edge_attribute.id = edge_attribute_value.edge_attribute_id) as own_attrs, 
	(SELECT json_group_object(edge_type_attribute.name, COALESCE(edge_type_attribute_value.value, edge_type_attribute.default_value)) FILTER (where edge_type_attribute.id is not null)  FROM edge_type_attribute
	LEFT JOIN edge_type_attribute_value
	ON edge_type_attribute_value.edge_id = edge.id 
	AND edge_type_attribute.id = edge_type_attribute_value.edge_type_attribute_id WHERE  edge_type_attribute.edge_type_id = edge_type.id) as type_attrs
	FROM edge
	LEFT JOIN edge_type
	ON edge_type.id = edge.edge_type_id
	INNER JOIN node source
	ON source.id = edge.source_node_id
	INNER JOIN node target
	ON target.id = edge.target_node_id
	INNER JOIN node_type source_node_type
	ON source_node_type.id = source.node_type_id
	INNER JOIN node_type target_node_type
	ON target_node_type.id = target.node_type_id
	INNER JOIN node_type_anchor source_anchor
	ON source_anchor.node_type_id = source_node_type.id
	AND source_anchor.id = edge.source_node_type_anchor_id
	INNER JOIN node_type_anchor target_anchor
	ON target_anchor.node_type_id = target_node_type.id
	AND target_anchor.id = edge.target_node_type_anchor_id


	GROUP BY edge.id
	ORDER BY edge_type.layer_level ASC) all_edges

	LEFT JOIN edge_waypoint wps
	ON wps.edge_id = all_edges.edge_id
	GROUP BY all_edges.edge_id;
SQL);

$dbh->exec(<<<SQL


	INSERT INTO edge_type(id,name) VALUES 
		(1, "arrow"),
		(2, "annotation");

	INSERT INTO node_type(id,name, layer_level, decorator_path) VALUES 
		(1, "rect", 10, "M 0 0 L 100 100 M 100 0 L 0 100"),
		(2, "text", 11, ""),
		(3, "ellipse", 10, ""),
		(4, "group", 5, ""),
		(5, "interface", 8, "");

	INSERT INTO node(id,node_type_id,x0,y0,x1,y1) VALUES 
		(1, 1, 20, 70, 30, 100),
		(2, 1, 70, 20, 60, 80),
		(3, 2, 50, 40, 30, 60),
		(4, 3, 20, 20, 40, 40),
		(5, 4, 0, 0, 120, 120),
		(6, 5, 130, 30, 200, 70);

	INSERT INTO node_attribute(id, name, type, default_value) VALUES 
		(1, "fill", "color", "lightblue"),
		(2, "stroke", "color", "darkblue"),
		(3, "stroke-width", "length", "2"),
		(4, "stroke-dasharray", "list(length)", "");

	INSERT INTO node_type_anchor(node_type_id, x_percent, y_percent, x_offset, y_offset, width_percent, height_percent, width_const, height_const) VALUES 
		(1, 0.5, 0.5, 0, 0, 0, 0, 2, 2), 
		(1, 0, 0, 0, 0, 0, 0, 2, 2), 
		(1, 1, 0, 0, 0, 0, 0, 2, 2), 
		(1, 1, 1, 0, 0, 0, 0, 2, 2), 
		(1, 0, 1, 0, 0, 0, 0, 2, 2), 
		(1, 0.5, 1, 0, 0, 0, 0, 2, 2), 
		(1, 0.5, 0, 0, 0, 0, 0, 2, 2), 
		(1, 1, 0.5, 0, 0, 0, 0, 2, 2), 
		(1, 0, 0.5, 0, 0, 0, 0, 2, 2), 
		(3, 0.5, 0.5, 0, 0, 0, 0, 5, 5), 
		(2, 0.5, 0.5, 0, 5, 0, 0, 4,4), 
		(5, 0.5, 0.5, 0, 0, 0, 0, 5, 5);

	INSERT INTO node_type_region(node_type_id, x_percent, y_percent, x_offset, y_offset, width_percent, height_percent, width_const, height_const, decorator_path) VALUES 
		(5, 1, 0.5, 5, 0, 0, 1, 10, 0, "M 100 0 L 0 0 L 0 100 L 100 100"), 
		(5, 0, 0.5, -5, 0, 0, 1, 10, 0, "M 0 0 L 100 0 L 100 100 L 0 100"), 
		(5, 0.5, 0.5, 0, 0, 1, 1, -20.5, 0, "");

	INSERT INTO node_attribute_value (node_id, node_attribute_id, value) VALUES
		(1, 1, "red");

	INSERT INTO node_type_attribute(id, node_type_id, name, type, default_value) VALUES 
		(1, 2, "text", "text", "?"),
		(2, 2, "font-size", "length", "12"),
		(3, 4, "fill", "color", "#eee"),
		(4, 4, "stroke", "color", "#aaa"),
		(5, 4, "stroke-width", "length", "1"),
		(6, 1, "rx", "percent", "50"),
		(7, 1, "ry", "percent", "23");

	INSERT INTO node_type_attribute_value (node_id, node_type_id, node_type_attribute_id, value) VALUES
		(3, 2, 1, "foo"),
		(3, 2, 2, "6");

	INSERT INTO edge(edge_type_id, source_node_id, source_node_type_id, source_node_type_anchor_id,
		target_node_id, target_node_type_id, target_node_type_anchor_id) VALUES 
		(1, 1, 1, 2, 2, 1, 7),
		(2, 1, 1, 6, 3, 2, 11);

	INSERT INTO edge_attribute(id, name, type, default_value) VALUES 
		(1, "stroke", "color", "black"),
		(2, "stroke-width", "length", "1"),
		(3, "stroke-dasharray", "list(length)", ""),
		(4, "stroke-linecap", "enum(round,square)", "round"),
		(5, "smoothness", "scalar", "1");

	INSERT INTO edge_waypoint(edge_id, sort, x, y) VALUES
		(1,1, 50, 100),
		(1,2, 40, 90),
		(1,3, 50, 70);
SQL);

$stmtNodes = $dbh->prepare('SELECT * FROM view_shape');
$stmtNodes->execute();
$nodes = array_map(function ($row) {
	return (object) [
		...$row,
		'own_attrs' => json_decode($row['own_attrs']),
		'type_attrs' => json_decode($row['type_attrs']),
		'combied_attrs' => (object)[...json_decode($row['own_attrs'], true), ...json_decode($row['type_attrs'], true)],
		'anchors' => json_decode($row['anchors']),
		'regions' => json_decode($row['regions']),
	];
}, $stmtNodes->fetchAll());

$stmtEdges = $dbh->prepare('SELECT * FROM view_edges');
$stmtEdges->execute();
$edges = array_map(function ($row) {
	return (object) [
		...$row,
		'own_attrs' => json_decode($row['own_attrs']),
		'type_attrs' => json_decode($row['type_attrs']),
		'combied_attrs' => (object)[...json_decode($row['own_attrs'], true), ...json_decode($row['type_attrs'], true)],
		'waypoints' => json_decode($row['waypoints']),
		'bounding' => json_decode($row['bounding']),
	];
}, $stmtEdges->fetchAll());

$stmtVB = $dbh->prepare('SELECT MIN(MIN(x0, x1)) as minx, MAX(MAX(x0, x1)) as maxx, MIN(MIN(y0, y1)) as miny, MAX(MAX(y0, y1)) as maxy FROM node');
$stmtVB->execute();
$viewbox = (object)$stmtVB->fetch()


?>
<!DOCTYPE html>
<html>
	<head>
		<meta charset="utf-8">
		<meta http-equiv="X-UA-Compatible" content="IE=edge">
		<title>SQL2SVG</title>
		<style>
			* {
				box-sizing: border-box;
			}
			:root {
				font-family: sans-serif;
			}

			svg.outer {
				border: 1px solid black;
				width: 100%;
				display: block;
			}

			body {
				display: grid;
				justify-content: center;
				justify-items: stretch;
				grid-template-areas: 1fr;
				overflow-y: scroll;
			}

			summary {
				cursor: pointer;
			}

			pre {
				padding: 1em;
				background: #eee;
				widows: 10;
				width: 100%;
				overflow: auto;
			}

			section {
				max-width: 45em;
				width: 100%;
			}
		</style>
	</head>
	<body>
		
		<section>
			<h2>Drawing</h2>

		<svg class="outer" viewBox="<?php echo $viewbox->minx-5 ?> <?php echo $viewbox->miny-5 ?> <?php echo $viewbox->maxx - $viewbox->minx + 10 ?> <?php echo $viewbox->maxy - $viewbox->miny + 10 ?>" width="800" height="500">
			<?php foreach ($nodes as $node): ?>
				<svg 
					x="<?php echo $node->minx ?>" 
					y="<?php echo $node->miny ?>" 
					width="<?php echo $node->width ?>" 
					height="<?php echo $node->height ?>" overflow="auto">
				<title>node-<?php echo $node->node_id ?></title>
				<?php if ($node->type == 'rect'): ?>
					<rect 
					<?php if (!$node->combied_attrs->rx || !$node->combied_attrs->ry): ?>
					shape-rendering="crispEdges"
					<?php endif ?>
					vector-effect="non-scaling-stroke"
					x="0" 
					y="0" 
					width="100%" 
					height="100%"
					fill="<?php echo $node->combied_attrs->fill ?>"
					rx="<?php echo $node->combied_attrs->rx ?>%"
					ry="<?php echo $node->combied_attrs->ry ?>%"
					stroke="<?php echo $node->combied_attrs->stroke ?>"
					stroke-dasharray="<?php echo $node->combied_attrs->{"stroke-dasharray"} ?>"
					stroke-width="<?php echo $node->combied_attrs->{"stroke-width"} ?>">
					</rect>
				<?php elseif ($node->type == 'ellipse'): ?>
					<ellipse 
					vector-effect="non-scaling-stroke"
					cx="50%" 
					cy="50%" 
					rx="50%" 
					ry="50%"
					fill="<?php echo $node->combied_attrs->fill ?>"
					stroke="<?php echo $node->combied_attrs->stroke ?>"
					stroke-dasharray="<?php echo $node->combied_attrs->{"stroke-dasharray"} ?>"
					stroke-width="<?php echo $node->combied_attrs->{"stroke-width"} ?>">
					</ellipse>
				<?php elseif ($node->type == 'group'): ?>
					<rect shape-rendering="crispEdges"
					vector-effect="non-scaling-stroke"
					x="0" 
					y="0" 
					width="100%" 
					height="100%"
					fill="<?php echo $node->combied_attrs->fill ?>"
					stroke="<?php echo $node->combied_attrs->stroke ?>"
					stroke-dasharray="<?php echo $node->combied_attrs->{"stroke-dasharray"} ?>"
					stroke-width="<?php echo $node->combied_attrs->{"stroke-width"} ?>">
					</rect>
				<?php elseif ($node->type == 'interface'): ?>
					<rect shape-rendering="crispEdges"
					vector-effect="non-scaling-stroke"
					x="0" 
					y="0" 
					width="100%" 
					height="100%"
					fill="<?php echo $node->combied_attrs->fill ?>">
					</rect>
				<?php elseif($node->type == 'text'): ?>
						<rect
						vector-effect="non-scaling-stroke" fill="#eeea" x="0" y="0" width="100%" height="100%"
						stroke-width="1" stroke="blue" fill="#aaaa" opacity="0.3"
				stroke-dasharray="3 3"></rect>
						<text 
						x="50%" 
						y="50%" font-size="<?php echo $node->type_attrs->{"font-size"} ?>"
						text-anchor="middle"
						dominant-baseline="central">
							<?php echo $node->type_attrs->{"text"} ?>
						</text>
				<?php else: ?>
					<rect
					vector-effect="non-scaling-stroke" 
					x="0" 
					y="0" 
					width="100%" 
					height="100%"
					fill="#ccc"
					stroke="magenta"
					stroke-width="1"
					stroke-dasharray="1 1">
					</rect>
				<?php endif ?>

				<?php if ($node->decorator_path): ?>
					<svg stroke="none" viewBox="0 0 100 100" x="0" y="0" width="100%" height="100%" overflow="auto" preserveAspectRatio="none">
					<path fill="none" 
					stroke-linecap="round"
					vector-effect="non-scaling-stroke"  stroke="black" stroke-width="3" d="<?php echo $node->decorator_path ?>"/>
				    </svg>
				<?php endif ?>
				</svg>
				<?php foreach ($node->regions as $region): ?>
					<svg overflow="auto" x="<?php echo $region->cx - $region->width/2 ?>" 
						y="<?php echo $region->cy - $region->height/2 ?>" 
						width="<?php echo $region->width ?>" 
						height="<?php echo $region->height ?>">
						<rect
						x="0" y="0"
						width="100%" height="100%"
							opacity="0.5"
							shape-rendering="crispEdges"
							vector-effect="non-scaling-stroke"
							 fill="darkblue">
								<title>region-<?php echo $region->region_id ?></title>
							</rect>
							<svg stroke="none" viewBox="0 0 100 100" x="0" y="0" width="100%" height="100%" overflow="auto"  preserveAspectRatio="none">
							<path
					shape-rendering="crispEdges" fill="none" 
					vector-effect="non-scaling-stroke"  stroke="black" stroke-width="5" d="<?php echo $region->decorator_path ?>"/>
						    </svg>
					</svg>
				<?php endforeach ?>
				<?php foreach ($node->anchors as $anchor): ?>
					<rect
					shape-rendering="crispEdges"
					vector-effect="non-scaling-stroke"
					x="<?php echo $anchor->cx - $anchor->width/2 ?>" 
					y="<?php echo $anchor->cy - $anchor->height/2 ?>" 
					width="<?php echo $anchor->width ?>" 
					height="<?php echo $anchor->height ?>" 
					fill="orange" stroke="yellow" stroke-width="2">
						<title>anchor-<?php echo $anchor->anchor_id ?></title>
					</rect>
				<?php endforeach ?>
			<?php endforeach ?>
			<?php foreach ($edges as $edge): ?>
				<rect shape-rendering="crispEdges"
				opacity="0.3"
				stroke-dasharray="3 3"
					vector-effect="non-scaling-stroke" stroke-width="1" stroke="blue" fill="#aaaa" x="<?php echo $edge->bounding->minx ?>" y="<?php echo $edge->bounding->miny ?>"
					width="<?php echo $edge->bounding->maxx - $edge->bounding->minx ?>" height="<?php echo $edge->bounding->maxy -  $edge->bounding->miny ?>">
						<title>bounding-edge-<?php echo $edge->edge_id ?></title>
					</rect>
				<path  shape-rendering="optimizeQuality"
					fill="none"
					stroke="<?php echo $edge->combied_attrs->stroke ?>"
					stroke-width="<?php echo $edge->combied_attrs->{"stroke-width"} ?>"
					stroke-dasharray="<?php echo $edge->combied_attrs->{"stroke-dasharray"} ?>"
					stroke-linecap="<?php echo $edge->combied_attrs->{"stroke-linecap"} ?>"
					vector-effect="non-scaling-stroke"
					d="<?php echo make_path($edge->combied_attrs->smoothness,
						[(object)['x'=>$edge->source_x, 'y' => $edge->source_y], ...$edge->waypoints, 
							(object)['x'=>$edge->target_x, 'y' => $edge->target_y]
						]) ?>"></path>

				<?php foreach ($edge->waypoints as $i => $wp): ?>
				 	<circle
					vector-effect="non-scaling-stroke" cx="<?php echo $wp->x ?>" cy="<?php echo $wp->y ?>" r="0.4" fill="magenta" />
				 <?php endforeach ?>
			<?php endforeach ?>
		</svg>

		<details>
			<summary>Nodes</summary>
			<pre><?php echo json_encode($nodes,  JSON_PRETTY_PRINT) ?></pre>
		</details>
		<details>
			<summary>Edges</summary>
			<pre><?php echo json_encode($edges,  JSON_PRETTY_PRINT) ?></pre>
		</details>
		</section>
	</body>
</html>