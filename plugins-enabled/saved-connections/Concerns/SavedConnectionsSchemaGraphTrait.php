<?php

trait SavedConnectionsSchemaGraphTrait
{
    private const SCHEMA_GRAPH_PARAM = 'schema_graph';
    private const SCHEMA_GRAPH_VIEW_PARAM = 'schema_graph_view';
    private const SCHEMA_GRAPH_DEFAULT_VIEW = 'keys';

    private function schemaGraphHead(): void
    {
        $url = defined('Adminer\\ME') && defined('Adminer\\DB') && Adminer\DB !== ''
            ? Adminer\ME.self::SCHEMA_GRAPH_PARAM.'=1'
            : '';

        $config = array(
            'url' => $url,
            'active' => isset($_GET[self::SCHEMA_GRAPH_PARAM]),
        );

        echo Adminer\script(
            'window.AdminerSchemaGraphConfig = '.
            json_encode(
                $config,
                JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
            ).
            ';'
        );

        echo Adminer\script(<<<'JS'
document.addEventListener('DOMContentLoaded', function () {
    var config = window.AdminerSchemaGraphConfig || {};
    if (!config.url) {
        return;
    }

    var linkGroups = Array.prototype.slice.call(document.querySelectorAll('p.links'));
    var target = linkGroups.find(function (group) {
        return group.querySelector('a[href*="sql="], a[href*="import="], a#dump, a[href*="create="]');
    });

    if (!target || target.querySelector('[data-schema-graph-link]')) {
        return;
    }

    var link = document.createElement('a');
    link.href = config.url;
    link.textContent = 'Schema graph';
    link.dataset.schemaGraphLink = '1';
    if (config.active) {
        link.className = 'active';
    }

    target.appendChild(document.createTextNode('\n'));
    target.appendChild(link);
});
JS);
    }

    private function schemaGraphHomepage(): bool
    {
        if (!isset($_GET[self::SCHEMA_GRAPH_PARAM])) {
            return false;
        }

        $this->printSchemaGraphPage();

        return true;
    }

    private function printSchemaGraphPage(): void
    {
        $view = $this->schemaGraphView();
        $graph = $this->schemaGraphData($view);

        echo '<h2>Schema graph</h2>'."\n";
        echo "<p class='links'><a href='".Adminer\h(Adminer\ME)."'>Tables</a>\n";
        foreach ($this->schemaGraphViews() as $key => $label) {
            echo '<a href="'.Adminer\h($this->schemaGraphViewUrl($key)).'"'.($view === $key ? ' class="active"' : '').'>'.Adminer\h($label)."</a>\n";
        }
        echo "</p>\n";

        if (!$graph['tables']) {
            echo "<p class='message'>No tables found in this schema.</p>\n";

            return;
        }

        echo "<style>\n";
        echo ".schema-graph-wrap{position:relative;overflow:hidden;width:100%;max-width:100%;box-sizing:border-box;height:min(72vh,900px);min-height:420px;border:1px solid #ddd;background:#fff;touch-action:none;}\n";
        echo ".schema-graph-wrap svg{position:absolute;top:0;left:0;display:block;max-width:none!important;user-select:none;transform-origin:0 0;}\n";
        echo ".schema-graph-wrap.is-dragging svg{cursor:grabbing;}\n";
        echo ".schema-graph-wrap:not(.is-dragging) svg{cursor:grab;}\n";
        echo ".schema-graph-source{width:100%;box-sizing:border-box;font-family:monospace;}\n";
        echo ".schema-graph-group{margin-top:1.5rem;}\n";
        echo "</style>\n";

        echo '<p>'.Adminer\h((string) count($graph['tables'])).' tables, '.Adminer\h((string) $graph['columns']).' shown columns, '.Adminer\h((string) count($graph['relations'])).' foreign-key relations, '.Adminer\h((string) count($graph['components']))." groups.</p>\n";

        foreach ($graph['components'] as $index => $component) {
            $title = 'Group '.($index + 1).' - '.count($component['tables']).' table'.(count($component['tables']) === 1 ? '' : 's').', '.count($component['relations']).' relation'.(count($component['relations']) === 1 ? '' : 's');
            echo '<section class="schema-graph-group">';
            echo '<h3>'.Adminer\h($title)."</h3>\n";
            echo "<div class='schema-graph-wrap'>\n";
            echo '<pre class="mermaid">'.Adminer\h($component['mermaid'])."</pre>\n";
            echo "</div>\n";
            echo "<details><summary>Mermaid source</summary>\n";
            echo '<textarea class="schema-graph-source" rows="16" spellcheck="false">'.Adminer\h($component['mermaid'])."</textarea>\n";
            echo "</details>\n";
            echo "</section>\n";
        }

        echo Adminer\script_src('https://cdn.jsdelivr.net/npm/mermaid@11/dist/mermaid.min.js');
        echo Adminer\script(<<<'JS'
if (window.mermaid) {
    mermaid.initialize({
        startOnLoad: false,
        securityLevel: 'loose',
        maxTextSize: 5000000,
        flowchart: {
            htmlLabels: true,
            useMaxWidth: false
        },
        er: {
            useMaxWidth: false
        }
    });

    mermaid.run({ querySelector: '.mermaid' }).then(function () {
        requestAnimationFrame(initSchemaGraphPanZoom);
    }).catch(function (error) {
        console.error(error);
    });
}

function initSchemaGraphPanZoom() {
    document.querySelectorAll('.schema-graph-wrap').forEach(function (wrap) {
        var svg = wrap.querySelector('svg');
        if (!svg || svg.dataset.schemaGraphPanZoom) {
            return;
        }

        svg.dataset.schemaGraphPanZoom = '1';
        var state = {
            x: 0,
            y: 0,
            scale: 1,
            manual: false,
            dragging: false,
            pointerId: null,
            dragX: 0,
            dragY: 0,
            startX: 0,
            startY: 0,
            lastWidth: 0,
            lastHeight: 0
        };

        var size = graphSize(svg);
        svg.setAttribute('width', size.width);
        svg.setAttribute('height', size.height);

        function apply() {
            svg.style.transform = 'translate(' + state.x + 'px, ' + state.y + 'px) scale(' + state.scale + ')';
        }

        function fit() {
            var rect = wrap.getBoundingClientRect();
            var padding = 24;
            var nextScale = Math.min(
                (rect.width - padding * 2) / size.width,
                (rect.height - padding * 2) / size.height
            );

            state.scale = clamp(nextScale, 0.02, 2);
            state.x = (rect.width - size.width * state.scale) / 2;
            state.y = (rect.height - size.height * state.scale) / 2;
            state.lastWidth = rect.width;
            state.lastHeight = rect.height;
            apply();
        }

        function preserveCenterOnResize() {
            var rect = wrap.getBoundingClientRect();
            if (!state.lastWidth || !state.lastHeight) {
                fit();
                return;
            }

            var graphCenterX = (state.lastWidth / 2 - state.x) / state.scale;
            var graphCenterY = (state.lastHeight / 2 - state.y) / state.scale;
            state.x = rect.width / 2 - graphCenterX * state.scale;
            state.y = rect.height / 2 - graphCenterY * state.scale;
            state.lastWidth = rect.width;
            state.lastHeight = rect.height;
            apply();
        }

        wrap.addEventListener('wheel', function (event) {
            event.preventDefault();
            state.manual = true;

            var rect = wrap.getBoundingClientRect();
            var pointerX = event.clientX - rect.left;
            var pointerY = event.clientY - rect.top;
            var graphX = (pointerX - state.x) / state.scale;
            var graphY = (pointerY - state.y) / state.scale;
            var nextScale = clamp(state.scale * Math.exp(-event.deltaY * 0.001), 0.02, 6);

            state.scale = nextScale;
            state.x = pointerX - graphX * state.scale;
            state.y = pointerY - graphY * state.scale;
            apply();
        }, { passive: false });

        wrap.addEventListener('pointerdown', function (event) {
            if (event.button !== 0 || !event.target.closest('svg')) {
                return;
            }

            state.manual = true;
            state.dragging = true;
            state.pointerId = event.pointerId;
            state.dragX = event.clientX;
            state.dragY = event.clientY;
            state.startX = state.x;
            state.startY = state.y;
            wrap.classList.add('is-dragging');
            wrap.setPointerCapture(event.pointerId);
        });

        wrap.addEventListener('pointermove', function (event) {
            if (!state.dragging || event.pointerId !== state.pointerId) {
                return;
            }

            state.x = state.startX + event.clientX - state.dragX;
            state.y = state.startY + event.clientY - state.dragY;
            apply();
        });

        function stopDragging(event) {
            if (!state.dragging || event.pointerId !== state.pointerId) {
                return;
            }

            state.dragging = false;
            state.pointerId = null;
            wrap.classList.remove('is-dragging');
        }

        wrap.addEventListener('pointerup', stopDragging);
        wrap.addEventListener('pointercancel', stopDragging);
        wrap.addEventListener('dblclick', function () {
            state.manual = false;
            fit();
        });

        if (window.ResizeObserver) {
            var resizeTimer = null;
            new ResizeObserver(function () {
                clearTimeout(resizeTimer);
                resizeTimer = setTimeout(function () {
                    if (state.manual) {
                        preserveCenterOnResize();
                    } else {
                        fit();
                    }
                }, 50);
            }).observe(wrap);
        } else {
            window.addEventListener('resize', function () {
                if (state.manual) {
                    preserveCenterOnResize();
                } else {
                    fit();
                }
            });
        }

        fit();
    });
}

function graphSize(svg) {
    var viewBox = svg.viewBox && svg.viewBox.baseVal;
    if (viewBox && viewBox.width && viewBox.height) {
        return { width: viewBox.width, height: viewBox.height };
    }

    var box = svg.getBBox();
    return {
        width: Math.max(box.width, 1),
        height: Math.max(box.height, 1)
    };
}

function clamp(value, min, max) {
    return Math.min(max, Math.max(min, value));
}
JS);
    }

    private function schemaGraphData(string $view): array
    {
        $tables = array();
        $fieldsByTable = array();
        $relations = array();

        foreach (Adminer\table_status('', true) as $table => $status) {
            $table = (string) $table;
            $tables[] = $table;
            $fieldsByTable[$table] = Adminer\fields($table);
        }

        sort($tables, SORT_NATURAL | SORT_FLAG_CASE);

        foreach ($tables as $table) {
            foreach ((array) Adminer\adminer()->foreignKeys($table) as $foreignKey) {
                $target = (string) ($foreignKey['table'] ?? '');
                if ($target === '' || !in_array($target, $tables, true)) {
                    continue;
                }

                $sourceColumns = array_values((array) ($foreignKey['source'] ?? array()));
                $targetColumns = array_values((array) ($foreignKey['target'] ?? array()));
                $ratio = $this->schemaGraphRelationRatio($table, $sourceColumns, $fieldsByTable[$table]);

                foreach ($sourceColumns as $index => $sourceColumn) {
                    $targetColumn = $targetColumns[$index] ?? null;
                    if ($targetColumn === null) {
                        continue;
                    }

                    $relations[] = array(
                        $table,
                        (string) $sourceColumn,
                        $target,
                        (string) $targetColumn,
                        $ratio,
                    );
                }
            }
        }

        usort($relations, function (array $left, array $right): int {
            return strcmp(implode('|', $left), implode('|', $right));
        });

        $components = $this->schemaGraphComponents($tables, $relations);

        return array(
            'tables' => $tables,
            'relations' => $relations,
            'columns' => $this->schemaGraphShownColumnCount($components, $fieldsByTable, $relations, $view),
            'components' => array_map(function (array $component) use ($fieldsByTable, $relations, $view): array {
                $componentRelations = array_values(array_filter($relations, function (array $relation) use ($component): bool {
                    return in_array($relation[0], $component, true) && in_array($relation[2], $component, true);
                }));

                return array(
                    'tables' => $component,
                    'relations' => $componentRelations,
                    'mermaid' => $this->schemaGraphComponentMermaid($component, $fieldsByTable, $componentRelations, $view),
                );
            }, $components),
        );
    }

    private function schemaGraphComponentMermaid(array $tables, array $fieldsByTable, array $relations, string $view): string
    {
        $tableIds = array();
        $lines = array('erDiagram');

        foreach ($tables as $table) {
            $tableIds[$table] = $this->schemaGraphNodeId($table, $tableIds);
        }

        foreach ($tables as $table) {
            $shownColumns = $this->schemaGraphShownColumns($table, $fieldsByTable[$table], $relations, $view);
            $lines[] = '    '.$tableIds[$table].' {';

            if ($view === 'tables') {
                $lines[] = '        table '.$this->schemaGraphErToken($table).' "'.$this->schemaGraphLabel($table).'"';
                $lines[] = '    }';
                continue;
            }

            if (!$shownColumns) {
                $lines[] = '        string no_columns_visible';
            }

            foreach ($shownColumns as $field) {
                $lines[] = '        '.$this->schemaGraphErColumn($field);
            }

            $lines[] = '    }';
        }

        foreach ($relations as $relation) {
            $lines[] = '    '.$tableIds[$relation[2]].' '.$this->schemaGraphErRelationship($relation[4]).' '.$tableIds[$relation[0]].' : "'.$this->schemaGraphLabel($relation[4]).'"';
        }

        return implode("\n", $lines)."\n";
    }

    private function schemaGraphComponents(array $tables, array $relations): array
    {
        $neighbors = array();
        foreach ($tables as $table) {
            $neighbors[$table] = array();
        }

        foreach ($relations as $relation) {
            $neighbors[$relation[0]][$relation[2]] = true;
            $neighbors[$relation[2]][$relation[0]] = true;
        }

        $seen = array();
        $components = array();
        foreach ($tables as $table) {
            if (isset($seen[$table])) {
                continue;
            }

            $queue = array($table);
            $seen[$table] = true;
            $component = array();

            while ($queue) {
                $current = array_shift($queue);
                $component[] = $current;

                foreach (array_keys($neighbors[$current]) as $neighbor) {
                    if (!isset($seen[$neighbor])) {
                        $seen[$neighbor] = true;
                        $queue[] = $neighbor;
                    }
                }
            }

            sort($component, SORT_NATURAL | SORT_FLAG_CASE);
            $components[] = $component;
        }

        usort($components, function (array $left, array $right): int {
            return count($right) <=> count($left) ?: strcmp(implode('|', $left), implode('|', $right));
        });

        return $components;
    }

    private function schemaGraphShownColumns(string $table, array $fields, array $relations, string $view): array
    {
        if ($view === 'tables') {
            return array();
        }

        if ($view === 'all') {
            return $this->schemaGraphMarkForeignKeyColumns($table, $fields, $relations);
        }

        $columns = array();
        foreach ($fields as $column => $field) {
            if (!empty($field['primary'])) {
                $columns[$column] = $field;
            }
        }

        foreach ($relations as $relation) {
            if ($relation[0] === $table && isset($fields[$relation[1]])) {
                $columns[$relation[1]] = $fields[$relation[1]];
            }
            if ($relation[2] === $table && isset($fields[$relation[3]])) {
                $columns[$relation[3]] = $fields[$relation[3]];
            }
        }

        return $this->schemaGraphMarkForeignKeyColumns($table, array_intersect_key($fields, $columns), $relations);
    }

    private function schemaGraphMarkForeignKeyColumns(string $table, array $fields, array $relations): array
    {
        foreach ($relations as $relation) {
            if ($relation[0] === $table && isset($fields[$relation[1]])) {
                $fields[$relation[1]]['_schema_graph_fk'] = true;
            }
        }

        return $fields;
    }

    private function schemaGraphShownColumnCount(array $components, array $fieldsByTable, array $relations, string $view): int
    {
        $count = 0;

        foreach ($components as $component) {
            $componentRelations = array_values(array_filter($relations, function (array $relation) use ($component): bool {
                return in_array($relation[0], $component, true) && in_array($relation[2], $component, true);
            }));

            foreach ($component as $table) {
                $count += count($this->schemaGraphShownColumns($table, $fieldsByTable[$table], $componentRelations, $view));
            }
        }

        return $count;
    }

    private function schemaGraphView(): string
    {
        $view = (string) ($_GET[self::SCHEMA_GRAPH_VIEW_PARAM] ?? self::SCHEMA_GRAPH_DEFAULT_VIEW);

        return array_key_exists($view, $this->schemaGraphViews()) ? $view : self::SCHEMA_GRAPH_DEFAULT_VIEW;
    }

    private function schemaGraphViews(): array
    {
        return array(
            'tables' => 'Tables only',
            'keys' => 'Key columns',
            'all' => 'All columns',
        );
    }

    private function schemaGraphViewUrl(string $view): string
    {
        return Adminer\ME.self::SCHEMA_GRAPH_PARAM.'=1&'.self::SCHEMA_GRAPH_VIEW_PARAM.'='.urlencode($view);
    }

    private function schemaGraphNodeId(string $table, array $existing): string
    {
        $id = 'T_'.preg_replace('~[^A-Za-z0-9_]~', '_', $table);
        if (!preg_match('~^T_[A-Za-z_]~', $id)) {
            $id = 'T_table_'.$id;
        }

        $base = $id;
        $counter = 2;
        while (in_array($id, $existing, true)) {
            $id = $base.'_'.$counter;
            $counter++;
        }

        return $id;
    }

    private function schemaGraphErColumn(array $field): string
    {
        $name = (string) ($field['field'] ?? '');
        $type = (string) (($field['type'] ?? '') ?: ($field['full_type'] ?? ''));
        $fullType = (string) (($field['full_type'] ?? '') ?: $type);
        $keys = array();

        if (!empty($field['primary'])) {
            $keys[] = 'PK';
        }
        if (!empty($field['_schema_graph_fk'])) {
            $keys[] = 'FK';
        }

        $comment = trim($fullType.(!empty($field['null']) ? ' NULL' : ''));

        return trim(
            $this->schemaGraphErToken($type ?: 'value').' '.
            $this->schemaGraphErToken($name ?: 'column').' '.
            implode(',', $keys).' '.
            ($comment !== '' ? '"'.$this->schemaGraphLabel($comment).'"' : '')
        );
    }

    private function schemaGraphErRelationship(string $ratio): string
    {
        switch ($ratio) {
            case '1:1':
                return '||--o|';
            case '0..1:1':
                return 'o|--o|';
            case '0..N:1':
                return 'o|--o{';
            case 'N:1':
            default:
                return '||--o{';
        }
    }

    private function schemaGraphErToken(string $value): string
    {
        $token = preg_replace('~[^A-Za-z0-9_]~', '_', $value);
        $token = trim((string) $token, '_');

        if ($token === '') {
            $token = 'value';
        }
        if (!preg_match('~^[A-Za-z_]~', $token)) {
            $token = 'v_'.$token;
        }

        return $token;
    }

    private function schemaGraphRelationRatio(string $table, array $sourceColumns, array $fields): string
    {
        $unique = $this->schemaGraphColumnsAreUnique($table, $sourceColumns);
        $nullable = false;

        foreach ($sourceColumns as $column) {
            if (!empty($fields[$column]['null'])) {
                $nullable = true;
                break;
            }
        }

        if ($unique) {
            return ($nullable ? '0..1' : '1').':1';
        }

        return ($nullable ? '0..N' : 'N').':1';
    }

    private function schemaGraphColumnsAreUnique(string $table, array $columns): bool
    {
        sort($columns, SORT_STRING);

        foreach (Adminer\indexes($table) as $index) {
            if (!in_array($index['type'] ?? '', array('PRIMARY', 'UNIQUE'), true)) {
                continue;
            }

            $indexColumns = array_values((array) ($index['columns'] ?? array()));
            sort($indexColumns, SORT_STRING);

            if ($indexColumns === $columns) {
                return true;
            }
        }

        return false;
    }

    private function schemaGraphLabel(string $value): string
    {
        return str_replace(array('\\', '"', "\r", "\n"), array('\\\\', '\\"', ' ', ' '), $value);
    }
}
