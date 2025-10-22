<?php
namespace Mervit\iDoklad;

use Mervit\iDoklad\Exceptions\IDokladException;

class Endpoint
{
    /**
     * @var Client
     */
    protected $client;

    /**
     * @var string
     */
    protected $resource;

    public function __construct(Client $client, $resource)
    {
        $this->client = $client;
        $this->resource = $resource;
    }

    /**
     * List records from the endpoint.
     *
     * @param array<string,mixed> $params
     * @return array<string,mixed>|null
     * @throws IDokladException
     */
    public function list(array $params = [])
    {
        return $this->client->request('GET', $this->resource, ['query' => $params]);
    }

    /**
     * Retrieve a single record by identifier.
     *
     * @param int|string $id
     * @param array<string,mixed> $params
     * @return array<string,mixed>|null
     * @throws IDokladException
     */
    public function detail($id, array $params = [])
    {
        return $this->client->request('GET', $this->resource . '/' . $id, ['query' => $params]);
    }

    /**
     * Create a new resource.
     *
     * @param array<string,mixed> $payload
     * @return array<string,mixed>|null
     * @throws IDokladException
     */
    public function create(array $payload)
    {
        return $this->client->request('POST', $this->resource, ['json' => $payload]);
    }

    /**
     * Update an existing resource.
     *
     * @param int|string $id
     * @param array<string,mixed> $payload
     * @return array<string,mixed>|null
     * @throws IDokladException
     */
    public function update($id, array $payload)
    {
        return $this->client->request('PUT', $this->resource . '/' . $id, ['json' => $payload]);
    }

    /**
     * Delete a resource.
     *
     * @param int|string $id
     * @return array<string,mixed>|null
     * @throws IDokladException
     */
    public function delete($id)
    {
        return $this->client->request('DELETE', $this->resource . '/' . $id);
    }

    /**
     * Execute a custom action endpoint.
     *
     * @param int|string $id
     * @param string $action
     * @param array<string,mixed> $payload
     * @return array<string,mixed>|null
     * @throws IDokladException
     */
    public function action($id, $action, array $payload = [])
    {
        $path = rtrim($this->resource . '/' . $id, '/') . '/' . ltrim($action, '/');
        $options = [];

        if (!empty($payload)) {
            $options['json'] = $payload;
        }

        return $this->client->request('POST', $path, $options);
    }
}
