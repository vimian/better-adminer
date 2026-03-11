<?php

trait SavedConnectionsAuthFormTrait
{
    public function loginFormField($name, $heading, $input)
    {
        return $heading.$input."\n";
    }

    public function loginForm()
    {
        $driver = defined('Adminer\\DRIVER') ? (string) constant('Adminer\\DRIVER') : 'server';
        $server = defined('Adminer\\SERVER') ? (string) constant('Adminer\\SERVER') : '';
        $db = defined('Adminer\\DB') ? (string) constant('Adminer\\DB') : '';
        $username = (string) ($_GET['username'] ?? '');

        echo "<table class='layout'>\n";
        echo Adminer\adminer()->loginFormField(
            'driver',
            '<tr><th>'.Adminer\lang(33).'<td>',
            Adminer\html_select('auth[driver]', Adminer\SqlDriver::$drivers, $driver, 'loginDriver(this);')
        );
        echo Adminer\adminer()->loginFormField(
            'server',
            '<tr><th>'.Adminer\lang(34).'<td>',
            '<input name="auth[server]" value="'.Adminer\h($server).'" title="hostname[:port]" placeholder="localhost" autocapitalize="off">'
        );
        echo Adminer\adminer()->loginFormField(
            'username',
            '<tr><th>'.Adminer\lang(35).'<td>',
            '<input name="auth[username]" id="username" autofocus value="'.Adminer\h($username).'" autocomplete="username" autocapitalize="off">'.
            Adminer\script("const authDriver = qs('#username').form['auth[driver]']; authDriver && authDriver.onchange();")
        );
        echo Adminer\adminer()->loginFormField(
            'password',
            '<tr><th>'.Adminer\lang(36).'<td>',
            '<input type="password" name="auth[password]" autocomplete="current-password">'
        );
        echo Adminer\adminer()->loginFormField(
            'db',
            '<tr><th>'.Adminer\lang(37).'<td>',
            '<input name="auth[db]" value="'.Adminer\h($db).'" autocapitalize="off">'
        );
        echo "</table>\n";
        echo $this->saveAndLoginRow();

        return true;
    }

    private function saveAndLoginRow(): string
    {
        return <<<'HTML'
<p>
    <input type="submit" value="Login" class="saved-connections-launcher" data-saved-connections-save>
</p>
HTML;
    }
}
