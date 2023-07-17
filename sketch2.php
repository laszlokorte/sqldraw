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

	CREATE TABLE element (
		id INTEGER PRIMARY KEY AUTOINCREMENT
	);

	CREATE TABLE shape (
		id INTEGER PRIMARY KEY AUTOINCREMENT,
		base_width INTEGER,
		base_height INTEGER
	);

	CREATE TABLE shape_anchor (
		id INTEGER PRIMARY KEY AUTOINCREMENT,
		shape_id INTEGER,

		CONSTRAINT fk_shape_id FOREIGN KEY (shape_id) REFERENCES shape(id)
	);

	CREATE TABLE node (
		id INTEGER PRIMARY KEY AUTOINCREMENT,
		element_id INTEGER,
		shape_id INTEGER,
		position_x INTEGER,
		position_y INTEGER,

		CONSTRAINT unq_element_id UNIQUE (element_id),
		CONSTRAINT fk_element_id FOREIGN KEY (element_id) REFERENCES element(id)
		CONSTRAINT fk_shape_id FOREIGN KEY (shape_id) REFERENCES shape(id)
	);

	CREATE TABLE text (
		id INTEGER PRIMARY KEY AUTOINCREMENT,
		element_id INTEGER,
		content TEXT,

		CONSTRAINT fk_element_id FOREIGN KEY (element_id) REFERENCES element(id)
	);

	CREATE TABLE text_position (
		id INTEGER PRIMARY KEY AUTOINCREMENT,
		text_id INTEGER,
		position_x INTEGER,
		position_y INTEGER,

		CONSTRAINT unq_text_id_sort UNIQUE (text_id),
		CONSTRAINT fk_text_id FOREIGN KEY (text_id) REFERENCES text(id)
	);

	CREATE TABLE edge (
		id INTEGER PRIMARY KEY AUTOINCREMENT,
		element_id INTEGER,
		source_node_id INTEGER,
		source_shape_id INTEGER,
		source_anchor_id INTEGER,
		target_node_id INTEGER,
		target_shape_id INTEGER,
		target_anchor_id INTEGER,

		CONSTRAINT unq_element_id UNIQUE (element_id),
		CONSTRAINT fk_element_id FOREIGN KEY (element_id) REFERENCES element(id),
		CONSTRAINT fk_source_id FOREIGN KEY (source_node_id, source_shape_id) REFERENCES node(id, shape_id),
		CONSTRAINT fk_target_id FOREIGN KEY (target_node_id, target_shape_id) REFERENCES node(id, shape_id),
		CONSTRAINT fk_source_anchor_id FOREIGN KEY (source_shape_id, source_anchor_id) REFERENCES shape_anchor(source_shape_id, source_anchor_id),
		CONSTRAINT fk_target_anchor_id FOREIGN KEY (target_shape_id, target_anchor_id) REFERENCES shape_anchor(target_shape_id, target_anchor_id)
	);

	CREATE TABLE path_point(
		id INTEGER PRIMARY KEY AUTOINCREMENT,
		element_id INTEGER,
		x INTEGER,
		y INTEGER,
		sort INTEGER,

		CONSTRAINT unq_element_id_sort UNIQUE (element_id, sort),
		CONSTRAINT fk_element_id FOREIGN KEY (element_id) REFERENCES element(id)
	);

	CREATE TABLE text_style (
		id INTEGER PRIMARY KEY AUTOINCREMENT,
		text_id INTEGER,

		CONSTRAINT unq_text_id_sort UNIQUE (text_id),
		CONSTRAINT fk_text_id FOREIGN KEY (text_id) REFERENCES text(id)
	);

	CREATE TABLE edge_style (
		id INTEGER PRIMARY KEY AUTOINCREMENT,
		edge_id INTEGER,

		CONSTRAINT unq_edge_id_sort UNIQUE (edge_id),
		CONSTRAINT fk_edge_id FOREIGN KEY (edge_id) REFERENCES edge(id)
	);

	CREATE TABLE node_style (
		id INTEGER PRIMARY KEY AUTOINCREMENT,
		node_id INTEGER,

		CONSTRAINT unq_node_id_sort UNIQUE (node_id),
		CONSTRAINT fk_node_id FOREIGN KEY (node_id) REFERENCES node(id)
	);
SQL);

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

	</body>
</html>