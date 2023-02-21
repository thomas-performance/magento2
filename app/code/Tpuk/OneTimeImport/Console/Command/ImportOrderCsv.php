<?php

declare(strict_types=1);

namespace Tpuk\OneTimeImport\Console\Command;

use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterfaceFactory;
use Magento\Framework\File\Csv;
use Magento\Sales\Api\Data\OrderAddressInterfaceFactory;
use Magento\Sales\Api\Data\OrderAddressInterface;
use Magento\Sales\Api\Data\OrderInterfaceFactory;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderItemInterfaceFactory;
use Magento\Sales\Api\Data\OrderPaymentInterfaceFactory;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Store\Model\StoreManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ImportOrderCsv extends Command
{
    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var CustomerInterfaceFactory
     */
    private $customerFactory;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var OrderInterfaceFactory
     */
    private $orderFactory;

    /**
     * @var Csv
     */
    private $csvReader;

    /**
     * @var OrderPaymentInterfaceFactory
     */
    private $orderPaymentFactory;

    /**
     * @var OrderItemInterfaceFactory
     */
    private $orderItemFactory;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var OrderAddressInterfaceFactory
     */
    private $orderAddressFactory;

    /**
     * @todo what is state 'Reversed'
     */
    const STATE_MAP = [
        'Complete' => Order::STATE_COMPLETE,
        'Shipped' => Order::STATE_PROCESSING,
        'Reversed' => Order::STATE_CANCELED,
        'Refunded' => Order::STATE_CLOSED,
        'Canceled' => Order::STATE_CANCELED,
        'Processing' => Order::STATE_PROCESSING,
        'Pending' => Order::STATE_PENDING_PAYMENT
    ];

    const COLUMNS = [
        'order_id' => 0,
        'customer_firstname' => 3,
        'customer_lastname' => 4,
        'customer_email' => 5,
        'payment_firstname' => 8,
        'payment_lastname' => 9,
        'payment_email' => 10,
        'payment_telephone' => 11,
        'payment_address_1' => 15,
        'payment_city' => 17,
        'payment_postcode' => 18,
        'payment_country_name' => 19,
        'payment_method' => 21,
        'shipping_firstname' => 24,
        'shipping_lastname' => 25,
        'shipping_email' => 26,
        'shipping_telephone' => 27,
        'shipping_address_1' => 31,
        'shipping_city' => 33,
        'shipping_postcode' => 34,
        'shipping_country_name' => 35,
        'shipping_amount' => 41,
        'tax_amount' => 42,
        'total' => 43,
        'order_status' => 45,
        'product_name' => 49,
        'option_sku' => 52,
        'model' => 53,
        'qty' => 54,
        'unit_price' => 55
    ];

    /**
     * @param CustomerRepositoryInterface $customerRepository
     * @param CustomerInterfaceFactory $customerFactory
     * @param OrderRepositoryInterface $orderRepository
     * @param OrderInterfaceFactory $orderFactory
     * @param Csv $csvReader
     * @param OrderPaymentInterfaceFactory $orderPaymentFactory
     * @param OrderItemInterfaceFactory $orderItemFactory
     * @param StoreManagerInterface $storeManager
     * @param OrderAddressInterfaceFactory $orderAddressFactory
     * @param string|null $name
     */
    public function __construct(
        CustomerRepositoryInterface $customerRepository,
        CustomerInterfaceFactory $customerFactory,
        OrderRepositoryInterface $orderRepository,
        OrderInterfaceFactory $orderFactory,
        Csv $csvReader,
        OrderPaymentInterfaceFactory $orderPaymentFactory,
        OrderItemInterfaceFactory $orderItemFactory,
        StoreManagerInterface $storeManager,
        OrderAddressInterfaceFactory $orderAddressFactory,
        string $name = null
    ) {
        $this->customerRepository = $customerRepository;
        $this->customerFactory = $customerFactory;
        $this->orderRepository = $orderRepository;
        $this->orderFactory = $orderFactory;
        $this->csvReader = $csvReader;
        $this->orderPaymentFactory = $orderPaymentFactory;
        $this->orderItemFactory = $orderItemFactory;
        $this->storeManager = $storeManager;
        $this->orderAddressFactory = $orderAddressFactory;
        parent::__construct();
    }

    /**
     * @return void
     */
    protected function configure()
    {
        $this->setName('tpuk:import');
        $this->setDescription('Single use - import customers and orders from pre-determined directory');
        $this->setDefinition([]);
        parent::configure();
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $ordersPath = __DIR__ . '/orders.csv';
        $customersPath = __DIR__ . '/customers.csv';

        try {
            $orders = $this->csvReader->getData($ordersPath);
        } catch (\Exception $exception) {
            $output->write(__('The file %1 does not exist. Stopping execution', $ordersPath));
            return;
        }

        try {
            $customersCsv = $this->csvReader->getData($customersPath);
        } catch (\Exception $exception) {
            $output->write(__('The file %1 does not exist. Stopping execution', $ordersPath));
            return;
        }

        unset($customersCsv[0]);
        $customers = array_map(function ($row) {
            return strtolower($row[1]);
        }, $customersCsv);

        $storeId = $this->storeManager->getDefaultStoreView()->getId();

        // Unset headings from data & reset array keys
        unset($orders[0]);
        $orders = array_values($orders);

        $order = null;
        $payment = null;
        $items = [];
        $createdCustomers = [];
        foreach ($orders as $row) {
            // Orders with multiple items may have several lines in CSV
            // Lines which represent a new order have an order ID
            // Lines missing order IDs are the next line of the previous order
            if ($row[self::COLUMNS['order_id']]) {
                if ($order) {
                    try {
                        $order->setItems($items);
                        $this->orderRepository->save($order);
                    } catch (\Exception $e) {
                        $output->writeln($e->getMessage());
                    }
                    $items = [];
                }
                $order = $this->orderFactory->create();
                $order->setStoreId($storeId);

                $email = strtolower($row[self::COLUMNS['customer_email']]);
                $firstname = $row[self::COLUMNS['customer_firstname']];
                $lastname = $row[self::COLUMNS['customer_lastname']];
                $order->setCustomerFirstname($firstname)
                    ->setCustomerLastname($lastname)
                    ->setCustomerEmail($email);

                // TODO map country to ISO2
                $shippingAddress = $this->orderAddressFactory->create();
                $shippingAddress->setAddressType('shipping')
                    ->setFirstname($row[self::COLUMNS['shipping_firstname']])
                    ->setLastname($row[self::COLUMNS['shipping_lastname']])
                    ->setEmail($row[self::COLUMNS['shipping_email']])
                    ->setTelephone($row[self::COLUMNS['shipping_telephone']])
                    ->setStreet($row[self::COLUMNS['shipping_address_1']])
                    ->setCity($row[self::COLUMNS['shipping_city']])
                    ->setPostcode($row[self::COLUMNS['shipping_postcode']])
                    ->setCountryId('UK');
                $order->setShippingAddress($shippingAddress);

                $billingAddress = $this->orderAddressFactory->create();
                $billingAddress->setAddressType('billing')
                    ->setFirstname($row[self::COLUMNS['payment_firstname']])
                    ->setLastname($row[self::COLUMNS['payment_lastname']])
                    ->setEmail($row[self::COLUMNS['payment_email']])
                    ->setTelephone($row[self::COLUMNS['payment_telephone']])
                    ->setStreet($row[self::COLUMNS['payment_address_1']])
                    ->setCity($row[self::COLUMNS['payment_city']])
                    ->setPostcode($row[self::COLUMNS['payment_postcode']])
                    ->setCountryId('UK');
                $order->setBillingAddress($billingAddress);

                if (array_search($email, $customers) && !array_search($email, $createdCustomers)) {
                    /** @var \Magento\Customer\Model\Customer $customer */
                    $customer = $this->customerFactory->create();
                    $customer->setFirstname($firstname)
                        ->setLastname($lastname)
                        ->setEmail($email)
                        ->setConfirmation(AccountManagementInterface::ACCOUNT_CONFIRMATION_REQUIRED)
                        ->setStoreId($storeId);

                    $this->customerRepository->save($customer);
                    $order->setCustomerIsGuest(0);
                    $createdCustomers[] = $email;
                } else {
                    $order->setCustomerIsGuest(1);
                }

                $payment = $this->orderPaymentFactory->create();
                $payment->setMethod($row[self::COLUMNS['payment_method']]);
                $order->setPayment($payment);

                $total = $this->priceStringToFloat($row[self::COLUMNS['total']]);
                $shippingAmount = $this->priceStringToFloat($row[self::COLUMNS['shipping_amount']]);

                $subtotal = $total - $shippingAmount;

                $order->setGrandTotal($total)
                    ->setBaseGrandTotal($total);

                $order->setShippingAmount($shippingAmount)
                    ->setBaseShippingAmount($shippingAmount);

                $tax = $row[self::COLUMNS['tax_amount']];

                if ($tax && $tax !== '') {
                    $tax = $this->priceStringToFloat($tax);
                    $order->setTaxAmount($tax)
                        ->setBaseTaxAmount($tax);
                    $subtotal -= $tax;
                }

                $order->setSubtotal($subtotal)
                    ->setBaseSubtotal($subtotal);

                $status = $row[self::COLUMNS['order_status']];
                $order->setStatus($status)
                    ->setState(self::STATE_MAP[$status]);
            }

            /** @var \Magento\Sales\Api\Data\OrderItemInterface $item */
            $item = $this->orderItemFactory->create();
            $sku = $row[self::COLUMNS['option_sku']] ?? $row[self::COLUMNS['model']];
            $qty = (float) $row[self::COLUMNS['qty']];

            $item->setSku($sku)
                ->setQtyOrdered($qty)
                ->setQtyInvoiced($qty)
                ->setQtyCanceled(0)
                ->setPrice((float)$row[self::COLUMNS['unit_price']])
                ->setName($row[self::COLUMNS['product_name']]);

            $items[] = $item;
        }
    }

    /**
     * @param string $price
     * @return float
     */
    private function priceStringToFloat(string $price): float
    {
        return (float) ltrim($price, '£');
    }
}
