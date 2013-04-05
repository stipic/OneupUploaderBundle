<?php

namespace Oneup\UploaderBundle\Uploader\Storage;

use Symfony\Component\HttpFoundation\File\File;
use Gaufrette\Stream\Local as LocalStream;
use Gaufrette\StreamMode;
use Gaufrette\Filesystem;

use Oneup\UploaderBundle\Uploader\Storage\StorageInterface;

class GaufretteStorage implements StorageInterface
{
    protected $filesystem;
    
    public function __construct(Filesystem $filesystem)
    {
        $this->filesystem = $filesystem;
    }
    
    public function upload(File $file, $name = null, $path = null)
    {
        $name = is_null($name) ? $file->getRelativePathname() : $name;
        $path = is_null($path) ? $name : sprintf('%s/%s', $path, $name);
        
        $src = new LocalStream($file->getPathname());
        $dst = $this->filesystem->createStream($path);
        
        // this is a somehow ugly workaround introduced
        // because the stream-mode is not able to create
        // subdirectories.
        if(!$this->filesystem->has($path))
            $this->filesystem->write($path, '', true);
        
        $src->open(new StreamMode('rb+'));
        $dst->open(new StreamMode('ab+'));
            
        while(!$src->eof())
        {
            $data = $src->read(100000);
            $written = $dst->write($data);
        }
            
        $dst->close();
        $src->close();
        
        return $this->filesystem->get($path);
    }
    
    public function remove($path)
    {
        try
        {
            $this->filesystem->delete($path);
        }
        catch(\RuntimeException $e)
        {
            return false;
        }
        
        return true;
    }
}