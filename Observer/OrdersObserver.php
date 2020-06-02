<?php

namespace Vsynch\LimitSales\Observer;

use Magento\Framework\Event\ObserverInterface;
use Psr\Log\LoggerInterface;


class OrdersObserver implements ObserverInterface
{
    protected $logger;
    protected $resource;
    protected $productRepo;

    public function __construct(LoggerInterface $logger, \Magento\Framework\App\ResourceConnection $resource,\Magento\Catalog\Model\ProductRepository $productRepository)
    {
        $this->resources = $resource;
        $this->logger = $logger;
        $this->productRepo = $productRepository;
        // You can use dependency injection to get any class this observer may need.
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        try{
            $connection= $this->resources->getConnection();
            $table = 'limit_sales_instances';

            /*$data = [
                ['message' => 'Happy New Year'],
                ['message' => 'Merry Christmas']
            ];
            foreach ($data as $bind) {
                $setup->getConnection()
                    ->insertForce($setup->getTable('greeting_message'), $bind);
            }*/
            $order = $observer->getEvent()->getOrder();
            $order_id = $order->getIncrementId();
            $customer = $order->getCustomerId();
            foreach($order->getAllItems() as $item){
                $productId= $item->getProductId();

                $psl = $this->productRepo->getById($productId)->getData('limit_sales');

                $psltf = $this->productRepo->getById($productId)->getData('limit_sales_time_frame');

                $productQ = $item->getQtyOrdered();

                $proName = $item->getName(); // product name

                $query = "SELECT * FROM ".$table." WHERE product_id = ".$productId." AND user_id = ".$customer;
                $result1 = $connection->fetchAll($query);


                if(count($result1)==0 && !empty($psl)){
                    $json = [['order'=>$order_id,'quantity'=>$productQ,'time'=>time()]];

                    if($productQ>$psl) $this->logger->info('Limit Sales reference: A Sale has been made in violation of Product Sale Limit!Order ID:'.$order_id.', Product Name:'.$proName);

                    $tableData = ['user_id'=>$customer,'product_id'=>$productId,'sales'=>json_encode($json),'sales_start_time'=>date('Y-m-d H:i:s'),'sales_end_time'=>date('Y-m-d H:i:s')];
                    $connection->insertForce($table, $tableData);
                }
                elseif(!empty($psl)){
                    foreach($result1 as $result){

                        $json = json_decode($result['sales']);
                        $salestf = [];
                        $qtf = 0;

                        if(!empty($psltf) && $psltf!=-1){
                            foreach($json as $sale){
                                if($psltf>(time()-$sale->time)) $salestf[] = $sale;
                            }
                        }
                        elseif($psltf==-1) $salestf = $json;

                        $salestf[] = json_decode(json_encode(['order'=>$order_id,'quantity'=>$productQ,'time'=>time()]), FALSE);

                        foreach($salestf as $sale) $qtf += $sale->quantity;
                        if($qtf>$psl) $this->logger->info('Limit Sales reference: A Sale has been made in violation of Product Sale Limit!Order ID:'.$order_id.', Product Name:'.$proName);

                        $sales = json_encode($salestf);

                        $connection->update($table, ['sales' => $sales,'sales_start_time'=>date('Y-m-d H:i:s',$salestf[0]->time),'sales_end_time' => date('Y-m-d H:i:s')], 'instance_id='.$result['instance_id']);

                    }
                }

                $this->logger->info(json_encode(['oid'=>$order_id,'uid'=>$customer,'pid'=>$productId,'pq'=>$productQ,'psl'=>$psl,'psltf'=>$psltf,'ct'=>strtotime(date('Y-m-d H:i:s')),'db'=>$result1]));

            }
            $shippingAddress = $order->getShippingAddress(); // shipping address

        }catch (\Exception $e) {
            $this->logger->debug($e->getMessage());
        }
    }
}
