<?php declare(strict_types=1);

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

use DateTime;
use InvalidArgumentException;
use RuntimeException;

/**
 * Interface HttpClientInterface
 */
interface HttpClientInterface
{
    public function get(string $url): array;
}

/**
 * Class LaravelHttpClient
 */
class LaravelHttpClient implements HttpClientInterface
{
    private PendingRequest $client;

    public function __construct()
    {
        $this->client = Http::withHeaders(
            [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
        )
        ->timeout(30)
        ->retry(3, 100);
    }

    /**
     * Get the response from the HTTP client
     * 
     * @param string $url
     * @return array
     */
    public function get(string $url): array
    {
        $response = $this->client->get($url);
        
        if (!$response->successful()) 
        {
            throw new RuntimeException(
                "HTTP request failed: {$response->status()}"
            );
        }

        return $response->json();
    }
}

/**
 * Class BaseService
 */
abstract class BaseService
{
    protected HttpClientInterface $httpClient;

    /**
     * Constructor
     * Here I'm just initializing the http client but it could be initialized in the child class making it more flexible and easier to test
     * and specially scalable making it easier to change the http client and having multiple clients in the same service
     */
    public function __construct()
    {
        $this->httpClient = new LaravelHttpClient();
    }

    /**
     * Get the response from the HTTP client
     * 
     * @param string $url
     * @return array
     */ 
    protected function get(string $url): array
    {
        return $this->httpClient->get($url);
    }
}

trait BlogGettersAndSetters
{
    /**
     * Get the posts
     * 
     * @return array
     */
    public function getPosts(): array
    {
        return $this->posts;
    }

    /**
     * Get the comments
     * 
     * @return array
     */
    public function getComments(): array
    {
        return $this->comments;
    }

    /**
     * Get the blog content
     * 
     * @return array
     */
    public function getBlogContent(): array
    {
        return $this->blogContent;
    }

    /**
     * Set the posts
     * 
     * @param array $posts
     */
    public function setPosts(array $posts): void
    {
        $this->posts = $posts;
    }

    /**
     * Set the comments
     * 
     * @param array $comments
     */
    public function setComments(array $comments): void
    {
        $this->comments = $comments;
    }

    /**
     * Set the blog content
     * 
     * @param array $blogContent
     */
    public function setBlogContent(array $blogContent): void
    {
        $this->blogContent = $blogContent;
    }
}

/**
 * Trait SingletonTrait
 * 
 * Implements the Singleton design pattern in a trait to ensure a class has only one instance.
 */
trait SingletonTrait
{
    private static ?self $instance = null;

    public static function instance(): self
    {
        if (static::$instance === null)
        {
            static::$instance = new static();
        }

        return static::$instance;
    }
}

/**
 * Class BlogService
 */
class BlogService extends BaseService
{
    use BlogGettersAndSetters;
    use SingletonTrait;

    public const POSTS_URL = 'https://coderbyte.com/api/challenges/json/all-posts';
    public const COMMENTS_URL = 'https://coderbyte.com/api/challenges/json/all-comments';

    private array $posts = [];
    private array $comments = [];
    private array $blogContent = [];

    /**
     * Retrieve all posts from the posts URL
     * 
     * @return self
     */ 
    public function retrieveAllPosts(): self
    {
        try 
        {
            $posts = $this->get(self::POSTS_URL);
            $this->setPosts($posts);
        }

        catch (RuntimeException $e) 
        {
        
            $this->setPosts([]);
        }
        
        return $this;
    }

    /**
     * Retrieve all comments from the comments URL
     * 
     * @return self
     */
    public function retrieveAllComments(): self
    {
        try 
        {
            $comments = $this->get(self::COMMENTS_URL);
            $this->setComments($comments);
        }

        catch (RuntimeException $e) 
        {
            
            $this->setComments([]);
        }

        return $this;
    }

    /**
     * Retrieve all blog content from the posts and comments
     * 
     * @return self
     */
    public function retrieveBlogContent(): self
    {
        $this->retrieveAllPosts()->retrieveAllComments();

        $blogContent = [];
        foreach ($this->getPosts() as $post) 
        {
            $postId = $post['id'];
            
            if (!isset($blogContent[$postId])) 
            {
                $blogContent[$postId] = [
                    'id' => $postId,
                    'userId' => $post['userId'],
                    'title' => $post['title'],
                    'body' => $post['body'],
                    'created_at' => $post['created_at'],
                    'comments' => [],
                ];
            }

            foreach ($this->getComments() as $comment) 
            {
                if ($comment['postId'] != $postId) 
                {
                    continue;
                }

                $blogContent[$postId]['comments'][] = $comment;
            }
        }

        $this->setBlogContent($blogContent);

        return $this;
    }

    /**
     * Sort the blog content by id
     * 
     * @return self
     */
    public function sort(): self
    {
        $blogContent = $this->getBlogContent();

        usort($blogContent, fn($a, $b) => $a['id'] <=> $b['id']);

        $this->setBlogContent($blogContent);
        return $this;
    }

    /**
     * Filter the blog content based on the provided filters and exclude posts with less than one comment.
     * 
     * @param Filter[] $filters
     * @return self
     */
    public function filter(array $filters): self 
    {
        $blogContent = array_filter(
            array: $this->getBlogContent(),
            callback: function($data) use ($filters) 
            {
                if (count($data['comments']) < 1)
                {
                    return false;
                }

                foreach ($filters as $filter) 
                {
                    $key = $filter->getKey();
                    $operator = $filter->getOperator();
                    $value = $filter->getValue();

                    $isValid = match($operator) 
                    {
                        Filter::OPERATOR_EQUALS => $data[$key] == $value,
                        
                        Filter::OPERATOR_BETWEEN => function() use ($data, $key, $value) 
                        {
                            if (!is_array($value) || count($value) !== 2) 
                            {
                                throw new InvalidArgumentException('Between operator requires array with 2 values');
                            }
                            
                            $date = new DateTime($data[$key]);
                            $startDate = new DateTime($value[0]);
                            $endDate = new DateTime($value[1]);
                            
                            return $date >= $startDate && $date <= $endDate;
                        },
                        
                        Filter::OPERATOR_GREATER_THAN => $data[$key] > $value,
                        Filter::OPERATOR_LESS_THAN => $data[$key] < $value,
                        Filter::OPERATOR_GREATER_THAN_EQUALS => $data[$key] >= $value,
                        Filter::OPERATOR_LESS_THAN_EQUALS => $data[$key] <= $value,
                        
                        default => throw new InvalidArgumentException("Unsupported operator: {$operator}")
                    };

                    if (is_callable($isValid)) {
                        $isValid = $isValid();
                    }

                    if (!$isValid) {
                        return false;
                    }
                }

                return true;
            }
        );

        $this->setBlogContent($blogContent);

        return $this;
    }

    /**
     * Convert the blog content to a JSON string
     * 
     * @return string
     */
    public function toJson(): string
    {
        return json_encode($this->getBlogContent());
    }
}

/**
 * Class Filter
 */
class Filter
{
    public const OPERATOR_EQUALS = '=';
    public const OPERATOR_BETWEEN = 'between';
    public const OPERATOR_GREATER_THAN = '>';
    public const OPERATOR_LESS_THAN = '<';
    public const OPERATOR_GREATER_THAN_EQUALS = '>=';
    public const OPERATOR_LESS_THAN_EQUALS = '<=';

    public function __construct(
        private readonly string $key,
        private readonly string $operator,
        private readonly mixed $value,
    ) {}

    /**
     * Get the key
     * 
     * @return string
     */
    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * Get the operator
     * 
     * @return string
     */
    public function getOperator(): string
    {
        return $this->operator;
    }

    /**
     * Get the value
     * 
     * @return mixed
     */
    public function getValue(): mixed
    {
        return $this->value;
    }
}


echo BlogService::instance()
    ->retrieveBlogContent()
    ->filter([
        new Filter(
            key: 'userId',
            operator: Filter::OPERATOR_EQUALS,
            value: 1,
        ),
        new Filter(
            key: 'created_at',
            operator: Filter::OPERATOR_BETWEEN,
            value: ['2021-01-02', '2024-01-02'],
        ),
        new Filter(
            key: 'comments',
            operator: Filter::OPERATOR_GREATER_THAN,
            value: 0,
        ),
    ])
    ->sort()
    ->toJson();

    /**
     * Tests for BlogService and Filter classes
     */
    class BlogServiceTest extends \PHPUnit\Framework\TestCase
    {
        /**
         * Test instance creation of BlogService
         */
        public function testInstanceCreation()
        {
            $blogService = BlogService::instance();

            $this->assertInstanceOf(BlogService::class, $blogService);
        }

        /**
         * Test blog content retrieval
         */
        public function testRetrieveBlogContent()
        {
            $blogService = BlogService::instance();
            $content = $blogService->retrieveBlogContent();

            $this->assertNotEmpty($content);
        }

        /**
         * Test filtering functionality
         */
        public function testFiltering()
        {
            $filters = [
                new Filter(key: 'userId', operator: Filter::OPERATOR_EQUALS, value: 1),
                new Filter(key: 'created_at', operator: Filter::OPERATOR_BETWEEN, value: ['2021-01-02', '2024-01-02']),
                new Filter(key: 'comments', operator: Filter::OPERATOR_GREATER_THAN, value: 0),
            ];

            $blogService = BlogService::instance();
            $filteredContent = $blogService->retrieveBlogContent()->filter($filters);

            $this->assertNotEmpty($filteredContent);
        }

        /**
         * Test sorting functionality
         */
        public function testSorting()
        {
            $blogService = BlogService::instance();
            $sortedContent = $blogService->retrieveBlogContent()->sort();

            $this->assertNotEmpty($sortedContent);
        }

        /**
         * Test JSON conversion
         */
        public function testToJson()
        {
            $blogService = BlogService::instance();
            $jsonContent = $blogService->retrieveBlogContent()->toJson();

            $this->assertJson($jsonContent);
        }
    }

