<?php

namespace IPE\Filesystem;

interface Downloader
{
    /**
     * @param non-empty-string $url
     * @param non-empty-string $filename
     *
     * @throws \IPE\Exception\InvalidArgument
     * @throws \IPE\Exception\IO
     *
     * @return void
     */
    public function saveAs($url, $filename);

    /**
     * @param non-empty-string $url
     *
     * @throws \IPE\Exception\InvalidArgument
     * @throws \IPE\Exception\IO
     *
     * @return string
     */
    public function getContent($url);

    /**
     * @return \IPE\Filesystem
     */
    public function getFilesystem();
}
