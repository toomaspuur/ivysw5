<?php
/**
 * Implemented by HammerCode OÜ team https://www.hammercode.eu/
 *
 * @copyright HammerCode OÜ https://www.hammercode.eu/
 * @license proprietär
 * @link https://www.hammercode.eu/
 */

namespace IvyPaymentPlugin\Models;

use Shopware\Components\Model\ModelRepository;

class IvyTransactionRepository extends ModelRepository
{

    /**
     * Returns an instance of the \Doctrine\ORM\Query object which selects a list of PaymentPlugin
     *
     * @param array|null $filter
     * @param array|null $orderBy
     * @param int  $offset
     * @param int  $limit
     * @return \Doctrine\ORM\Query
     */
    public function getListQuery(array $filter = null, array$orderBy = null, $offset = 0, $limit = 100)
    {
        $builder = $this->getListQueryBuilder($filter, $orderBy);
        $builder->setFirstResult($offset)
            ->setMaxResults($limit);
        return $builder->getQuery();
    }

    /**
     * Helper function to create the query builder for the "getListQuery" function.
     * This function can be hooked to modify the query builder of the query object.
     *
     * @param array|null $filter
     * @param array|null $orderBy
     * @return \Doctrine\ORM\QueryBuilder
     */
    public function getListQueryBuilder(array $filter = null, array $orderBy = null)
    {
        $builder = $this->getEntityManager()->createQueryBuilder();

        $builder->select(['ivy_transaction'])
            ->from($this->getEntityName(), 'ivy_transaction');

        $this->addFilter($builder, $filter);
        $this->addOrderBy($builder, $orderBy);

        return $builder;
    }
}
