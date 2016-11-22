<?php

namespace NethServer\Tool;

/*
 * Copyright (C) 2012 Nethesis S.r.l.
 *
 * This script is part of NethServer.
 *
 * NethServer is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * NethServer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with NethServer.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * Validate user's password
 *
 * @author Davide Principi <davide.principi@nethesis.it>
 * @since 1.0
 */
class PamValidator implements \Nethgui\System\ValidatorInterface, \Nethgui\Utility\PhpConsumerInterface, \Nethgui\Log\LogConsumerInterface
{

    const DEFAULT_PASSWORD = 'Nethesis,1234';

    private $failure = array();

    /**
     *
     * @var \Nethgui\Log\LogInterface
     */
    private $log;

    /**
     *
     * @var \Nethgui\Utility\PhpWrapper
     */
    private $php;

    public function evaluate($args)
    {
        $username = $args[0];
        $password = $args[1];
        if (isset($args[2])) {
            $credentials = &$args[2];
        } else {
            $credentials = array();
        }

        if ( ! isset($username)) {
            throw new \LogicException(sprintf('%s: the username has not been set', __CLASS__), 1354179502);
        }

        $authenticated = $this->authenticate($username, $password, $credentials);

        if ( ! $authenticated) {

            if (isset($credentials['hasDefaultPassword']) && $credentials['hasDefaultPassword'] === TRUE) {
                $this->failure[] = array('PamValidator_HasDefaultPassword', array('username' => $username, 'password' => self::DEFAULT_PASSWORD));
            } else {
                $this->failure[] = array('PamValidator_InvalidCredentials', array('username' => $username));
            }
        }

        return $authenticated;
    }

    public function getFailureInfo()
    {
        return $this->failure;
    }

    public function getLog()
    {
        if ( ! isset($this->log)) {
            $this->log = new \Nethgui\Log\Nullog();
        }
        return $this->log;
    }

    public function setLog(\Nethgui\Log\LogInterface $log)
    {
        $this->log = $log;
        return $this;
    }

    public function setPhpWrapper(\Nethgui\Utility\PhpWrapper $object)
    {
        $this->php = $object;
        return $this;
    }

    public function getPhpWrapper()
    {
        if ( ! isset($this->php)) {
            $this->php = new \Nethgui\Utility\PhpWrapper(__CLASS__);
        }
        return $this->php;
    }

    private function parseGroupList($data)
    {
        $suffix = '@' .  \Nethgui\array_end(explode('.', \gethostname(), 2));
        $groupList = array();
        foreach(explode("\x00", $data) as $groupName) {
            $groupList[] = preg_replace(sprintf('/%s$/', preg_quote($suffix)), '', $groupName);
        }
        return $groupList;
    }

    private function authenticate($username, $password, &$credentials)
    {
        $authenticated = $this->pamAuthenticate($username, $password);

        if ($authenticated) {

            $exitCode = 0;
            $output = array();

            $command = sprintf('/usr/bin/id -G -z -n %s 2>&1', escapeshellarg($username));

            $this->getPhpWrapper()->exec($command, $output, $exitCode);

            if ($exitCode === 0) {
                $groups = $this->parseGroupList($output[0]);
                $this->getLog()->notice(sprintf('%s: additional %s groups: %s. Output: %s', __CLASS__, $username, implode(', ', $groups), var_export($output, TRUE)));
            } else {
                $this->getLog()->warning(sprintf('%s: failed to execute %s command. Code %d. Output: %s', __CLASS__, $command, $exitCode, implode("\n", $output)));
                $groups = array();
            }

            $credentials['groups'] = $groups;
            $credentials['username'] = $username;
            $credentials['hasDefaultPassword'] = $password === self::DEFAULT_PASSWORD;
        } else {
            // authentication failed: check silently if user has default password:
            $hasDefaultPassword = $this->pamAuthenticate($username, self::DEFAULT_PASSWORD);
            $credentials['hasDefaultPassword'] = $hasDefaultPassword;
        }

        return $authenticated;
    }

    private function pamAuthenticate($username, $password)
    {
        $processPipe = $this->getPhpWrapper()->popen('/usr/bin/sudo /sbin/e-smith/pam-authenticate-pw >/dev/null 2>&1', 'w');
        if ($processPipe === FALSE) {
            $this->getLog()->error(sprintf('%s: %s', __CLASS__, implode(' ', $this->getPhpWrapper()->error_get_last())));
            return FALSE;
        }
        $this->getPhpWrapper()->fwrite($processPipe, $username . "\n" . $password);
        $authenticated = $this->getPhpWrapper()->pclose($processPipe) === 0;
        return $authenticated;
    }

}
