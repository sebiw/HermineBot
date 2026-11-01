<?php

namespace App\Core\Logging;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;

/**
 * Sucht im Log. Direkt per DBAL: Die Suche in den Zusatzdaten (JSON) lässt sich in DQL nicht ausdrücken.
 */
class LogSearch
{
    const PER_PAGE = 100;

    public function __construct( private readonly Connection $connection )
    {
    }

    /**
     * @return array{rows: list<array{id: int, channel: string, level: string, message: string, context: mixed, createdAt: \DateTimeImmutable}>, total: int, page: int, pages: int}
     */
    public function search( LogFilter $filter ): array
    {
        $total = (int) $this->query( $filter )->select( 'COUNT(*)' )->executeQuery()->fetchOne();
        $pages = max( 1 , (int) ceil( $total / self::PER_PAGE ) );
        $page = min( $filter->page , $pages );
        $rows = $this->query( $filter )
            ->select( 'id' , 'channel' , 'level' , 'message' , 'context' , 'created_date_time' )
            ->orderBy( 'created_date_time' , 'DESC' )->addOrderBy( 'id' , 'DESC' )
            ->setFirstResult( ( $page - 1 ) * self::PER_PAGE )->setMaxResults( self::PER_PAGE )
            ->executeQuery()->fetchAllAssociative();
        return [
            'rows' => array_map( fn( array $row ) => [
                'id' => (int) $row['id'],
                'channel' => (string) $row['channel'],
                'level' => (string) $row['level'],
                'message' => (string) $row['message'],
                'context' => is_string( $row['context'] ) ? json_decode( $row['context'] , true ) : null,
                'createdAt' => new \DateTimeImmutable( (string) $row['created_date_time'] ),
            ] , $rows ),
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
        ];
    }

    /**
     * @return string[] channels that occur in the log, for the selection
     */
    public function channels(): array
    {
        return array_map( 'strval' , $this->connection->fetchFirstColumn( 'SELECT DISTINCT channel FROM log' ) );
    }

    private function query( LogFilter $filter ): QueryBuilder
    {
        $query = $this->connection->createQueryBuilder()->from( 'log' )
            ->where( 'level IN (:levels)' )->setParameter( 'levels' , $filter->logLevels() , ArrayParameterType::STRING );
        if( $filter->from !== null ){
            $query->andWhere( 'created_date_time >= :from' )->setParameter( 'from' , $filter->from->format( 'Y-m-d H:i:s' ) );
        }
        if( $filter->to !== null ){
            // „bis 12:30“ meint die ganze Minute
            $query->andWhere( 'created_date_time <= :to' )->setParameter( 'to' , $filter->to->format( 'Y-m-d H:i:59' ) );
        }
        if( $filter->channel !== '' ){
            $query->andWhere( 'channel = :channel' )->setParameter( 'channel' , $filter->channel );
        }
        if( $filter->text !== '' ){
            // JSON steht unter MySQL normalisiert, unter MariaDB als Text mit \u-Folgen: deshalb auch die maskierte Schreibweise suchen
            $escaped = substr( (string) json_encode( mb_strtolower( $filter->text ) ) , 1 , -1 );
            $query->andWhere( "LOWER(message) LIKE :text OR LOWER(CAST(context AS CHAR)) LIKE :text OR LOWER(CAST(context AS CHAR)) LIKE :textEscaped" )
                ->setParameter( 'text' , LogFilter::likePattern( $filter->text ) )
                ->setParameter( 'textEscaped' , LogFilter::likePattern( $escaped ) );
        }
        return $query;
    }
}
