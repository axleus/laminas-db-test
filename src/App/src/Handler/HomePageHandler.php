<?php

declare(strict_types=1);

namespace App\Handler;

use Axleus\Debug\Debug;
use Laminas\Db\Adapter\Mysql\Adapter;
use Laminas\Db\Metadata\Source\Factory;
use Laminas\Db\Sql\Ddl;
use Laminas\Db\Sql\Sql;
use Laminas\Diactoros\Response\HtmlResponse;
use Mezzio\Router\RouteResult;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use stdClass;

class HomePageHandler implements RequestHandlerInterface
{
    private const TABLE = 't1';
    private const C1    = 'c1';
    private const C2    = 'c2';
    private const C3    = 'c3';

    private Sql $sql;
    private $charSet = 'CHARACTER SET latin1 COLLATE latin1_danish_ci'; // any value that is not default to test change

    public function __construct(
        private $config,
        private $adapter
    ) {
        $this->sql = new Sql($this->adapter);
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $routeResult = $request->getAttribute(RouteResult::class);
        $params      = $request->getQueryParams();
        return match (
            Test::tryFrom($request->getQueryParams()['test'])
            ) {
            Test::Metadata     => $this->metaData($request),
            Test::TableGateway => $this->tableGateway($request),
            Test::NamedParams  => $this->namedParams($request),
            Test::NormalizeArg => $this->normalizeArg($request),
            default => new HtmlResponse(
                Debug::dump(
                    ['error' => 'unknown test'],
                    __METHOD__,
                    false,
                    false
                ),
            ),
        };
    }

    private function normalizeArg(ServerRequestInterface $request): ResponseInterface
    {
        return new HtmlResponse(
            Debug::dump(
                var: NormalizedArgument::buildArgument(200, NormalizedArgument::Literal),
                outputBuffered: false
            ),
        );
        //return new HtmlResponse(implode('/', NormalizedArgument::buildArgument(['id' => '1'], 'literal')) );
    }

    private function namedParams(ServerRequestInterface $request): ResponseInterface
    {
        $sql = new Sql($this->adapter);

        $insert = $sql->update('test');
        $insert->set([
            'name'  => ':name',
            'value' => ':value',
        ])->where(['id' => ':id']);
        $stmt = $sql->prepareStatementForSqlObject($insert);

        // positional parameters
        $stmt->execute([
            'foo',
            'bar',
            1,
        ]);

        //"mapped" named parameters
        $stmt->execute([
            'c_0'    => 'foo',
            'c_1'    => 'bar',
            'where1' => 1,
        ]);

        //real named parameters
        $stmt->execute([
            'id'    => 1,
            'name'  => 'foo',
            'value' => 'bar',
        ]);

        return new HtmlResponse(
            Debug::dbDebug($this->adapter, false),
        );
    }

    private function metaData(ServerRequestInterface $request): ResponseInterface
    {
        $this->createTable();
        return new HtmlResponse(
            Debug::dbDebug($this->adapter, false),
        );
    }

    private function tableGateway(ServerRequestInterface $request): ResponseInterface
    {
        $this->createTable();
        $gateway = new \Laminas\Db\TableGateway\TableGateway(
            static::TABLE,
            $this->adapter
        );
        $insertData = [
            static::C2 => 'test',
            static::C3 => 'test',
        ];
        $gateway->insert($insertData);

        return new HtmlResponse(
            Debug::dbDebug($this->adapter, false),
        );
    }

    private function createTable(): void
    {

        $metadata      = Factory::createSourceFromAdapter($this->adapter);
        $tables        = $metadata->getTableNames();
        $tableInstance = null;

        if (\in_array(static::TABLE, $tables, true)) {
            $tableInstance = $metadata->getTable(static::TABLE);
        }


        if ($tableInstance instanceof \Laminas\Db\Metadata\Object\TableObject) {
            $this->adapter->query(
                $this->sql->buildSqlString(
                    new Ddl\DropTable(static::TABLE) // All Ddl objects should accept a Metadata\TableObject
                ),
                Adapter::QUERY_MODE_EXECUTE
            );
        }
        $createTable = new Ddl\CreateTable(static::TABLE);

        $pk = new Ddl\Column\Integer(static::C1);
        $pk->setName(static::C1);
        $pk->setOption('AUTO_INCREMENT', true);
        $pk->setOption('UNSIGNED', true);
        $pk->setNullable(false);
        $createTable->addColumn($pk);
        $createTable->addColumn(new Ddl\Column\Varchar(static::C2, 255));
        $createTable->addColumn(new Ddl\Column\Text(static::C3));
        $createTable->addConstraint(new Ddl\Constraint\PrimaryKey(static::C1));

        $this->adapter->query(
            $this->sql->buildSqlString($createTable),
            Adapter::QUERY_MODE_EXECUTE
        );
    }
}
