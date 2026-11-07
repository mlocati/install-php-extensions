<?php

namespace IPE\Service;

use IPE\Exception;
use IPE\Filesystem\Command;
use IPE\OS;

final class LetsEncryptFixer
{
    /**
     * @var \IPE\Logger
     */
    private $logger;

    public function __construct(\IPE\Logger $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @throws \IPE\Exception\IO
     * @throws \IPE\Exception\SystemPackager
     *
     * @return bool true if fixed, false if OS didn't require the fix
     */
    public function fix(OS $os)
    {
        $this->logger->info("# Fixing Let's Encrypt certificates");
        $cert = $this->getCACertificatesToBeFixed($os);
        $patched = $cert === '' ? false : $this->fixCACertificates($os, $cert);
        if ($patched) {
            $this->logger->info("Let's Encrypt certificates have been fixed");
        } else {
            $this->logger->info("Let's Encrypt certificates didn't require to be fixed");
        }

        return $patched;
    }

    /**
     * @throws \IPE\Exception\IO
     * @throws \IPE\Exception\SystemPackager
     *
     * @return string
     */
    private function getCACertificatesToBeFixed(OS $os)
    {
        $fs = $os->getFilesystem();
        $conf = null;
        if ($os instanceof OS\Alpine) {
            if ($os->getNumericVersion() > 308) {
                return '';
            }
        } elseif ($os instanceof OS\Debian) {
            if ($os->getNumericVersion() > OS\Debian::STRETCH) {
                return '';
            }
            $conf = $fs->readFile('/etc/ca-certificates.conf');
            if ($this->certConfHasCert($conf, 'mozilla/DST_Root_CA_X3.crt') && !$this->certConfHasCert($conf, 'mozilla/ISRG_Root_X1.crt')) {
                $this->logger->debug('Installing newer ca-certificates package');
                $os->getSystemPackager()->installPackages(['ca-certificates']);
                $conf = null;
            }
        } else {
            return '';
        }
        if ($conf === null) {
            $conf = $fs->readFile('/etc/ca-certificates.conf');
        }
        if ($this->certConfHasCert($conf, 'mozilla/ISRG_Root_X1.crt') && $this->certConfHasCert($conf, 'mozilla/DST_Root_CA_X3.crt')) {
            return $conf;
        }

        return '';
    }

    /**
     * @param string $conf
     * @param non-empty-string $cert
     *
     * @return bool
     */
    private function certConfHasCert($conf, $cert)
    {
        return preg_match('/^[ \t*]*' . preg_quote($cert, '/') . '\s*$/m', $conf) === 1;
    }

    /**
     * @param non-empty-string $conf
     *
     * @throws \IPE\Exception\IO
     *
     * @return bool
     */
    private function fixCACertificates(OS $os, $conf)
    {
        $newConf = Regex::replace('/^[ \t]*mozilla\/DST_Root_CA_X3\.crt\s*$/m', '!\0', $conf);
        if ($newConf === $conf) {
            $this->logger->debug('The DST_Root_CA_X3 certificate is not currently in use');

            return false;
        }
        $this->logger->debug('Disabling the DST_Root_CA_X3 certificate', ['originalConf' => $conf, 'newConf' => $newConf]);
        $fs = $os->getFilesystem();
        $fs->writeFile('/etc/ca-certificates.conf', $newConf);
        $this->logger->debug('Refreshing the certificates');
        list($rc, $output) = $fs->run(Command::create('update-ca-certificates', ['-f']));
        if ($rc !== 0) {
            throw new Exception\IO("update-ca-certificates failed:\n{$output}");
        }
        $this->logger->debug('update-ca-certificates executed succesfully', ['output' => $output]);

        return true;
    }
}
