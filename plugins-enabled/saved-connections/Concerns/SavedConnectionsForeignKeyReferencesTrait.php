<?php

trait SavedConnectionsForeignKeyReferencesTrait
{
    private const FK_REFERENCES_PARAM = 'fk_refs';
    private const FK_REFERENCES_LIMIT = 50;

    public function selectVal($value, $link, array $field, $rawValue)
    {
        if ($link || $rawValue === null || !isset($_GET['select'], $field['field'])) {
            return null;
        }

        $targetTable = (string) $_GET['select'];
        $targetColumn = (string) $field['field'];
        $targetSchema = (string) ($_GET['ns'] ?? '');

        if (!$this->hasForeignKeyReferences($targetTable, $targetColumn, $targetSchema)) {
            return null;
        }

        $link = $this->foreignKeyReferencesUrl($targetTable, $targetColumn, $rawValue, $targetSchema);

        return $this->renderSelectValue($value, $link, $field, $rawValue);
    }

    public function homepage()
    {
        if (method_exists($this, 'schemaGraphHomepage') && $this->schemaGraphHomepage()) {
            return false;
        }

        if (!isset($_GET[self::FK_REFERENCES_PARAM])) {
            return null;
        }

        $this->printForeignKeyReferencesPage();

        return false;
    }

    private function foreignKeyReferencesUrl(string $targetTable, string $targetColumn, $rawValue, string $targetSchema): string
    {
        $params = array(
            self::FK_REFERENCES_PARAM => '1',
            'target_table' => $targetTable,
            'target_column' => $targetColumn,
            'target_value' => (string) $rawValue,
        );

        if ($targetSchema !== '') {
            $params['target_ns'] = $targetSchema;
        }

        return Adminer\ME.http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    private function renderSelectValue($value, string $link, array $field, $rawValue): string
    {
        $rendered = ($value === null
            ? '<i>NULL</i>'
            : (preg_match('~char|binary|boolean~', $field['type'] ?? '') && !preg_match('~var~', $field['type'] ?? '')
                ? "<code>$value</code>"
                : (preg_match('~json~', $field['type'] ?? '') ? "<code class='jush-js'>$value</code>" : $value)));

        if (Adminer\is_blob($field) && !Adminer\is_utf8((string) $value)) {
            $rendered = '<i>'.Adminer\lang(48, strlen((string) $rawValue)).'</i>';
        }

        return "<a href='".Adminer\h($link)."'>$rendered</a>";
    }

    private function printForeignKeyReferencesPage(): void
    {
        $targetTable = (string) ($_GET['target_table'] ?? '');
        $targetColumn = (string) ($_GET['target_column'] ?? '');
        $targetValue = (string) ($_GET['target_value'] ?? '');
        $targetSchema = (string) ($_GET['target_ns'] ?? ($_GET['ns'] ?? ''));

        if ($targetTable === '' || $targetColumn === '') {
            echo "<p class='error'>Missing foreign key reference target.</p>\n";

            return;
        }

        echo '<h2>Foreign key references</h2>'."\n";
        echo '<p>';
        echo '<b>'.Adminer\h($this->qualifiedName($targetSchema, $targetTable).'.'.$targetColumn).'</b>';
        echo ' = <code>'.Adminer\h($targetValue).'</code>';
        echo ' <a href="'.Adminer\h($this->selectUrl($targetTable, $targetColumn, $targetValue, $targetSchema)).'">Referenced row</a>';
        echo "</p>\n";

        $references = $this->foreignKeyReferences($targetTable, $targetColumn, $targetSchema);
        if (!$references) {
            echo "<p class='message'>No single-column foreign keys reference this column in the current schema.</p>\n";

            return;
        }

        foreach ($references as $reference) {
            $this->printForeignKeyReferenceRows($reference, $targetValue);
        }
    }

    private function foreignKeyReferences(string $targetTable, string $targetColumn, string $targetSchema): array
    {
        $references = array();

        foreach (Adminer\table_status('', true) as $table => $status) {
            foreach ((array) Adminer\adminer()->foreignKeys((string) $table) as $foreignKey) {
                if (
                    count((array) ($foreignKey['source'] ?? array())) !== 1 ||
                    count((array) ($foreignKey['target'] ?? array())) !== 1 ||
                    (string) $foreignKey['table'] !== $targetTable ||
                    ($targetColumn !== '' && (string) $foreignKey['target'][0] !== $targetColumn)
                ) {
                    continue;
                }

                $foreignSchema = (string) ($foreignKey['ns'] ?? '');
                if ($foreignSchema !== '' && $targetSchema !== '' && $foreignSchema !== $targetSchema) {
                    continue;
                }

                $references[] = array(
                    'table' => (string) $table,
                    'column' => (string) $foreignKey['source'][0],
                    'target_column' => (string) $foreignKey['target'][0],
                );
            }
        }

        usort($references, function (array $left, array $right): int {
            return strcmp($left['table'].'.'.$left['column'], $right['table'].'.'.$right['column']);
        });

        return $references;
    }

    private function hasForeignKeyReferences(string $targetTable, string $targetColumn, string $targetSchema): bool
    {
        static $referencesByTarget = array();

        $key = $targetSchema.'.'.$targetTable;
        if (!isset($referencesByTarget[$key])) {
            $referencesByTarget[$key] = array();

            foreach ($this->foreignKeyReferences($targetTable, '', $targetSchema) as $reference) {
                $referencesByTarget[$key][$reference['target_column']] = true;
            }
        }

        return !empty($referencesByTarget[$key][$targetColumn]);
    }

    private function printForeignKeyReferenceRows(array $reference, string $targetValue): void
    {
        $table = $reference['table'];
        $column = $reference['column'];
        $fields = Adminer\fields($table);
        $field = $fields[$column] ?? array('field' => $column, 'type' => '');
        $where = Adminer\idf_escape($column).' = '.$this->sqlLiteral($field, $targetValue);
        $count = Adminer\get_val('SELECT COUNT(*) FROM '.Adminer\table($table).' WHERE '.$where);
        $url = $this->selectUrl($table, $column, $targetValue);

        echo '<h3>';
        echo '<a href="'.Adminer\h($url).'">'.Adminer\h($table).'</a>';
        echo ' <span class="time">'.Adminer\h($column).' = '.Adminer\h($targetValue).'</span>';
        echo "</h3>\n";

        if (!$count) {
            echo "<p class='message'>No rows.</p>\n";

            return;
        }

        echo '<p>'.Adminer\h((string) $count).' row'.($count == 1 ? '' : 's');
        if ($count > self::FK_REFERENCES_LIMIT) {
            echo ' shown first '.self::FK_REFERENCES_LIMIT;
        }
        echo "</p>\n";

        $result = Adminer\driver()->select(
            $table,
            array('*'),
            array($where),
            array(),
            array(),
            self::FK_REFERENCES_LIMIT,
            0,
            false
        );

        if (!$result) {
            echo '<p class="error">'.Adminer\h(Adminer\error())."</p>\n";

            return;
        }

        Adminer\print_select_result($result, null, array(), self::FK_REFERENCES_LIMIT);
    }

    private function sqlLiteral(array $field, string $value): string
    {
        if (isset($field['type'])) {
            return Adminer\adminer()->processInput($field, $value);
        }

        return Adminer\q($value);
    }

    private function selectUrl(string $table, string $column, string $value, ?string $schema = null): string
    {
        $url = Adminer\ME.'select='.urlencode($table).Adminer\where_link(0, $column, $value);

        if ($schema !== null && $schema !== '') {
            if (preg_match('~([?&]ns=)[^&]*~', $url)) {
                $url = preg_replace('~([?&]ns=)[^&]*~', '${1}'.urlencode($schema), $url);
            } else {
                $url .= '&ns='.urlencode($schema);
            }
        }

        return $url;
    }

    private function qualifiedName(string $schema, string $table): string
    {
        return $schema !== '' ? $schema.'.'.$table : $table;
    }
}
