<?php

namespace Oneup\UploaderBundle\Controller;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\File\Exception\UploadException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

use Oneup\UploaderBundle\UploadEvents;
use Oneup\UploaderBundle\Event\PostPersistEvent;
use Oneup\UploaderBundle\Event\PostUploadEvent;
use Oneup\UploaderBundle\Controller\UploadControllerInterface;
use Oneup\UploaderBundle\Uploader\Storage\StorageInterface;
use Oneup\UploaderBundle\Uploader\Response\EmptyResponse;
use Oneup\UploaderBundle\Uploader\Response\ResponseInterface;

abstract class AbstractController implements UploadControllerInterface
{
    protected $container;
    protected $storage;
    protected $config;
    protected $type;
    
    public function __construct(ContainerInterface $container, StorageInterface $storage, array $config, $type)
    {
        $this->container = $container;
        $this->storage = $storage;
        $this->config = $config;
        $this->type = $type;
    }

    protected function handleUpload(UploadedFile $file)
    {
        $this->validate($file);
        
        // no error happend, proceed
        $namer = $this->container->get($this->config['namer']);
        $name  = $namer->name($file);
        
        // perform the real upload
        $uploaded = $this->storage->upload($file, $name);
        
        return $uploaded;
    }
    
    protected function dispatchEvents($uploaded, ResponseInterface $response)
    {
        $request = $this->container->get('request');
        
        $postUploadEvent = new PostUploadEvent($uploaded, $response, $request, $this->type, $this->config);
        $dispatcher->dispatch(UploadEvents::POST_UPLOAD, $postUploadEvent);
    
        if(!$this->config['use_orphanage'])
        {
            // dispatch post upload event
            $postPersistEvent = new PostPersistEvent($uploaded, $response, $request, $this->type, $this->config);
            $dispatcher->dispatch(UploadEvents::POST_PERSIST, $postPersistEvent);
        }
    }

    protected function validate(UploadedFile $file)
    {
        // check if the file size submited by the client is over the max size in our config
        if($file->getClientSize() > $this->config['max_size'])
            throw new UploadException('error.maxsize');
        
        $extension = pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION);
        
        // if this mapping defines at least one type of an allowed extension,
        // test if the current is in this array
        if(count($this->config['allowed_extensions']) > 0 && !in_array($extension, $this->config['allowed_extensions']))
            throw new UploadException('error.whitelist');
        
        // check if the current extension is mentioned in the disallowed types
        // and if so, throw an exception
        if(count($this->config['disallowed_extensions']) > 0 && in_array($extension, $this->config['disallowed_extensions']))
            throw new UploadException('error.blacklist');
        
    }
}