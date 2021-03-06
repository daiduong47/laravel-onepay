<?php
/**
 * Created by IntelliJ IDEA.
 * User: nuocgansoi
 * Date: 11/1/2017
 * Time: 4:50 PM
 */

namespace NuocGanSoi\LaravelOnepay\Helpers;

class OnepayHelper
{
    const REJECTED_CODE_PROCESSING_ORDER = 1;
    const REJECTED_CODE_PENDING_ORDER = 2;

    public function __construct()
    {
        //  Check order configs
        $orderConfigs = [
            'model',
            'customer_id',
            'item_id',
            'status.attribute',
            'status.pending',
            'status.processing',
            'status.paid',
            'status.canceled',
            'status.rejected',
        ];

        //  Check shop configs
        $itemConfigs = [
            'model',
            'price',
        ];

        $shop = array_keys(config('onepay.shop'));
        if (!count($shop)) {
            $this->throwError('shop');
        }

        foreach ($shop as $item) {
            foreach ($itemConfigs as $itemConfig) {
                if (!config("onepay.shop.{$item}.{$itemConfig}")) {
                    $this->throwError("shop {$item} {$itemConfig}");
                }
            }

            foreach ($orderConfigs as $orderConfig) {
                if (!config("onepay.shop.{$item}.order.{$orderConfig}")) {
                    $this->throwError("shop {$item} order {$orderConfig}");
                }
            }
        }
    }

    private function throwError($attribute)
    {
        abort(\Illuminate\Http\Response::HTTP_FAILED_DEPENDENCY, "Check your config: {$attribute}!!!");
    }

    /**
     * @param $model
     * @return \Illuminate\Foundation\Application|mixed|null
     */
    public function get_shop_instance($model)
    {
        $model = strtolower($model);
        $class = config("onepay.shop.{$model}.model");
        if (!$class) $this->throwError($model);

        return app($class);
    }

    /**
     * @param $model
     * @return \Illuminate\Config\Repository|mixed
     */
    public function get_price_attribute($model)
    {
        $model = strtolower($model);
        $priceAttr = config("onepay.shop.{$model}.price");
        if (!$priceAttr) $this->throwError($model);

        return $priceAttr;
    }

    /**
     * @param $stringHashData
     * @return string
     */
    public function secure_hash_encode($stringHashData)
    {
        return strtoupper(hash_hmac('SHA256', $stringHashData, pack('H*', config('onepay.secure_secret'))));
    }

    /**
     * @param $item
     * @return null
     */
    public function get_price($item)
    {
        $model = strtolower(class_basename($item));
        $attr = $this->get_price_attribute($model);

        return $item->{$attr};
    }

    public function price_2_amount($price)
    {
        return $price * config('onepay.amount_exchange');
    }

    /**
     * @param $model
     * @return \Illuminate\Foundation\Application|mixed|null
     * @internal param $model
     */
    public function get_order_instance($model)
    {
        return app(config("onepay.shop.{$model}.order.model"));
    }

    /**
     * @param $user
     * @param $item
     * @return mixed
     */
    public function create_or_update_order($user, $item)
    {
        $model = strtolower(class_basename($item));
        $statusAttr = config("onepay.shop.{$model}.order.status.attribute");
        $customerIdAttr = config("onepay.shop.{$model}.order.customer_id");
        $itemIdAttr = config("onepay.shop.{$model}.order.item_id");
        $statusPending = config("onepay.shop.{$model}.order.status.pending");
        $statusProcessing = config("onepay.shop.{$model}.order.status.processing");

        $orderInstance = $this->get_order_instance($model);
        $orders = $orderInstance->where($customerIdAttr, $user->id)
            ->where($itemIdAttr, $item->id)
            ->whereIn($statusAttr, [$statusProcessing, $statusPending])
            ->get();
        foreach ($orders as $order) {
            switch ($order->{$statusAttr}) {
                case $statusPending:
                    return [
                        'success' => false,
                        'message' => 'Bạn đã có 1 đơn hàng đang chờ duyệt',
                        'rejected_code' => static::REJECTED_CODE_PENDING_ORDER,
                        'order' => $order,
                    ];
                case $statusProcessing:
                    return [
                        'success' => false,
                        'message' => 'Bạn đã có 1 đơn hàng đang được xử lý',
                        'rejected_code' => static::REJECTED_CODE_PROCESSING_ORDER,
                        'order' => $order,
                    ];
                default:
                    break;
            }
        }

        $order = $this->get_order_instance($model)->create([
            $statusAttr => $statusPending,
            $customerIdAttr => $user->id,
            $itemIdAttr => $item->id,
        ]);

        return [
            'success' => true,
            'order' => $order,
        ];
    }
}
