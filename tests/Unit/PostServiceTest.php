<?php

namespace Quantum\Tests\Unit;

use Quantum\Libraries\Database\PaginatorInterface;
use Quantum\Libraries\Storage\UploadedFile;
use Quantum\Libraries\Storage\FileSystem;
use Quantum\Factory\ServiceFactory;
use Shared\Services\AuthService;
use Shared\Services\PostService;
use PHPUnit\Framework\TestCase;
use Shared\Models\Post;
use Quantum\Di\Di;
use Quantum\App;

class PostServiceTest extends TestCase
{
    const PER_PAGE = 10;
    const CURRENT_PAGE = 1;
    public $authService;
    public $postService;
    public $userId = 1;
    private $initialUser = [
        'email' => 'anonymous@qt.com',
        'password' => '$2y$12$4Y4/1a4308KEiGX/xo6vgO41szJuDHC7KhpG5nknx/xxnLZmvMyGi',
        'firstname' => 'Tom',
        'lastname' => 'Hunter',
        'role' => 'admin',
        'activation_token' => '',
        'remember_token' => '',
        'reset_token' => '',
        'access_token' => '',
        'refresh_token' => '',
        'otp' => '',
        'otp_expires' => '',
        'otp_token' => '',
    ];
    private $initialPosts = [
        [
            'user_id' => 1,
            'title' => 'Walt Disney',
            'content' => 'The way to get started is to quit talking and begin doing.',
            'image' => '',
            'updated_at' => '2021-05-08 23:11:00',
        ],
        [
            'user_id' => 1,
            'title' => 'Lorem ipsum dolor sit amet',
            'content' => 'Praesent hendrerit lobortis malesuada. Proin bibendum lacinia nunc ac aliquet.',
            'image' => '',
            'updated_at' => '2021-05-08 23:12:00',
        ],
        [
            'user_id' => 1,
            'title' => 'Aenean dui turpis',
            'content' => 'Etiam aliquet urna luctus, venenatis justo aliquam, hendrerit arcu.',
            'image' => '',
            'updated_at' => '2021-05-08 23:13:00',
        ],
        [
            'user_id' => 1,
            'title' => 'James Cameron',
            'content' => 'If you set your goals ridiculously high and it is a failure, you will fail above everyone else success.',
            'image' => '',
            'updated_at' => '2021-05-08 23:14:00',
        ]
    ];
    private $fs;
    private $user;

    public function setUp(): void
    {
        App::loadCoreFunctions(dirname(__DIR__, 2) . DS . 'vendor' . DS . 'quantum' . DS . 'framework' . DS . 'src' . DS . 'Helpers');

        App::setBaseDir(__DIR__ . DS . '_root');

        Di::loadDefinitions();

        $this->fs = Di::get(FileSystem::class);

        $this->authService = ServiceFactory::get(AuthService::class, ['shared' . DS . 'store', 'users']);

        $this->user = $this->authService->add($this->initialUser);

        $this->postService = ServiceFactory::get(PostService::class, ['shared' . DS . 'store', 'posts']);

        foreach ($this->initialPosts as $post) {
            $this->postService->addPost($post);
        }
    }

    public function tearDown(): void
    {
        $this->authService->deleteTable();
        $this->postService->deleteTable();
        $this->removeFolders();
    }

    public function testGetPosts()
    {
        $this->assertIsObject($this->postService);

        $posts = $this->postService->getPosts(self::PER_PAGE, self::CURRENT_PAGE);

        $this->assertInstanceOf(PaginatorInterface::class, $posts);

        $this->assertIsArray($posts->data());

        $this->assertCount(4, $posts->data());

        $post = $posts->data()[0];

        $this->assertIsObject($post);
    }

    public function testGetSinglePost()
    {
        $posts = $this->postService->getPosts(self::PER_PAGE, self::CURRENT_PAGE);

        $uuid = $posts->data()[0]->uuid;

        $post = $this->postService->getPost($uuid);

        $this->assertInstanceOf(Post::class, $post);

        $postData = $post->asArray();

        $this->assertArrayHasKey('title', $postData);

        $this->assertArrayHasKey('content', $postData);

        $this->assertArrayHasKey('updated_at', $postData);

    }

    public function testAddNewPost()
    {
        $date = date('Y-m-d H:i:s');

        $newPost = $this->postService->addPost([
            'user_id' => 1,
            'title' => 'Just another post',
            'content' => 'Content of just another post',
            'image' => '',
            'updated_at' => $date
        ]);

        $uuid = $newPost['uuid'];

        $post = $this->postService->getPost($uuid);

        $this->assertEquals('Just another post', $post->title);

        $this->assertEquals('Content of just another post', $post->content);

        $this->assertEquals($date, $post->updated_at);
    }

    public function testUpdatePost()
    {
        $date = date('Y-m-d H:i:s');

        $posts = $this->postService->getPosts(self::PER_PAGE, self::CURRENT_PAGE);

        $uuid = $posts->data()[0]->uuid;

        $this->postService->updatePost($uuid, [
            'title' => 'Walt Disney Jr.',
            'content' => 'The best way to get started is to quit talking and begin doing.',
            'image' => 'image.jpg',
            'updated_at' => $date
        ]);

        $post = $this->postService->getPost($uuid);

        $this->assertNotEquals('Lorem ipsum dolor sit amet', $post->title);

        $this->assertEquals('Walt Disney Jr.', $post->title);

        $this->assertEquals('The best way to get started is to quit talking and begin doing.', $post->content);

        $this->assertEquals('image.jpg', $post->image);

        $this->assertEquals($date, $post->updated_at);
    }

    public function testDeletePost()
    {
        $this->assertCount(4, $this->postService->getPosts(self::PER_PAGE, self::CURRENT_PAGE)->data());

        $post = $this->postService->addPost([
            'user_id' => 1,
            'title' => 'Just another post',
            'content' => 'Content of just another post',
            'image' => '',
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        $this->assertCount(5, $this->postService->getPosts(self::PER_PAGE, self::CURRENT_PAGE)->data());

        $this->postService->deletePost($post['uuid']);

        $this->assertCount(4, $this->postService->getPosts(self::PER_PAGE, self::CURRENT_PAGE)->data());
    }

    public function testSaveDeleteImage()
    {

        $this->fileMeta = [
            'size' => 500,
            'name' => 'foo.jpg',
            'tmp_name' => base_dir() . DS . 'tmp' . DS . 'php8fe1.tmp',
            'type' => 'image/jpg',
            'error' => 0,
        ];

        $uploadedFile = new UploadedFile($this->fileMeta);

        $image = $this->postService->saveImage($uploadedFile, $this->user->uuid, 'poster');

        $this->assertFileExists(uploads_dir() . DS . $this->user->uuid . DS . $image);

        $this->postService->deleteImage($this->user->uuid . DS . $image);

        $this->assertFileDoesNotExist(uploads_dir() . DS . $this->user->uuid . DS . $image);
    }

    private function removeFolders()
    {
        $uploadsFolder = $this->fs->glob(uploads_dir() . DS . '*');

        foreach ($uploadsFolder as $user_uuid) {
            $this->fs->removeDirectory($user_uuid);
        }
    }
}
