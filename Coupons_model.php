Модель для купонов, генерация купонов в срм и обмен купонами с сайтом


<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Coupons_model extends CRM_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    /**Get list of coupons or one coupon
     * @param string $id
     * @param array $where
     * @return mixed
     */
    public function get($id = '', $where = array())
    {
        $this->db->select('*');
        if (is_numeric($id)) {
            $this->db->where('coupon_id', $id);
            $client = $this->db->get('tblcoupons')->row();
            return $client;
        }
        $this->db->where($where);
        return $this->db->get('tblcoupons')->result_array();
    }

    public function getProducts($id = '', $where = array())
    {
        $this->db->select('*');
        if (is_numeric($id)) {
            $this->db->where('coupon_id', $id);
            $client = $this->db->get('tblcoupons_affix')->row();
            return $client;
        }
        $this->db->where($where);
        return $this->db->get('tblcoupons_affix')->result_array();
    }

    /**Get list of groups or one group
     * @param string $id
     * @param array $where
     * @return mixed
     */
    public function get_group($id = '', $where = array())
    {
        $this->db->select('*');
        if (is_numeric($id)) {
            $this->db->where('id', $id);
            $client = $this->db->get('tblcoupons_group')->row();
            return $client;
        }
        $this->db->where($where);
        return $this->db->get('tblcoupons_group')->result_array();
    }

    /**Get active coupon group
     * @return array
     */
    public function getActiveGroup()
    {
        $this->db->select('*');
        $this->db->where(array('coupon_publish' => '1'));
        $groups = $this->db->get('tblcoupons_group')->result_array();
        $temp = array();
        $now = strtotime("yesterday");
        foreach ($groups as $group) {
            if (strtotime($group['coupon_start_date']) < $now && strtotime($group['coupon_expire_date']) > $now) {
                $temp[] = $group;
            }
        }
        return $temp;
    }

    /**Get
     * @param $group_id
     * @return array
     */
    public function getActiveCoupons($group_id)
    {
        $this->db->select('*');
        $this->db->where(array('group_id' => $group_id));
        $coupons = $this->db->get('tblcoupons')->result_array();
        $temp = array();
        foreach ($coupons as $coupon) {
            if ($coupon['finished_after_used'] == '0') {
                $temp[] = $coupon;
            } else {
                if (!$coupon['used']) {
                    $temp[] = $coupon;
                }
            }
        }
        return $temp;
    }

    /**Check if coupon active
     * @param $coupon
     * @return int
     */
    public function checkActiveCoupon($coupon)
    {
        $coupon = str_replace(' ', '', $coupon);
        $this->db->where('coupon_code', $coupon);
        $this->db->where('coupon_publish', '1');
        $coupon_id = $this->db->get('tblcoupons')->row();
        $now = strtotime("today");
        if (isset($coupon_id->coupon_id)) {
            if ($coupon_id->coupon_start_date == '0000-00-00' && $coupon_id->coupon_expire_date != '0000-00-00') {
                if (strtotime($coupon_id->coupon_expire_date) >= $now) {
                    return $this->_activeCoupon($coupon_id);
                }
            } else if ($coupon_id->coupon_start_date != '0000-00-00' && $coupon_id->coupon_expire_date == '0000-00-00') {
                if (strtotime($coupon_id->coupon_start_date) <= $now) {
                    return $this->_activeCoupon($coupon_id);
                }
            } else if($coupon_id->coupon_start_date == '0000-00-00' && $coupon_id->coupon_expire_date == '0000-00-00') {
                    return $this->_activeCoupon($coupon_id);
            }else{
                if (strtotime($coupon_id->coupon_start_date) <= $now && strtotime($coupon_id->coupon_expire_date) >= $now) {
                    return $this->_activeCoupon($coupon_id);
                }
            }
        }
        return 0;
    }

    /**Check if coupon used
     * @param $coupon
     * @return int
     */
    private function _activeCoupon($coupon)
    {
        if ($coupon->finished_after_used == '1') {
            if ($coupon->used == '0') {
                return $coupon->coupon_id;
            }
        } else {
            return $coupon->coupon_id;
        }
        return 0;
    }

    /**Get categories ids like at site
     * @param $ids
     * @return string
     */
    public function getCategoryIn($ids)
    {
        if ($ids == 0) return $ids;
        $this->db->select('store_id');
        $this->db->where("id in ($ids)");
        $category_ids = $this->db->get('tblproductscategory')->result_array();
        $temp_ids = array();
        foreach ($category_ids as $category) {
            $temp_ids[] = $category['store_id'];
        }
        return implode(",", $temp_ids);
    }

    /**Generate Random Coupon Code
     * @param $coupon_number_symbol
     * @param $coupon_pre_suffix
     * @param $coupon_post_suffix
     * @return string
     */
    private function _generate_coupone_code($coupon_number_symbol, $coupon_pre_suffix, $coupon_post_suffix)
    {
        $generate_code = substr(md5(microtime()), 0, $coupon_number_symbol);
        if ($coupon_pre_suffix) $generate_code = $coupon_pre_suffix . '-' . $generate_code;
        if ($coupon_post_suffix) $generate_code = $generate_code . '-' . $coupon_post_suffix;
        return strtoupper($generate_code);
    }

    /**Generate and Save coupons
     * @param $data
     * @param $products
     * @param bool $add_exist
     * @return array
     */
    public function generate_coupons($data, $products, $add_exist = true)
    {
        $coupon_number = $data['coupon_number'];
        $coupon_number_symbol = $data['coupon_number_symbol'];
        $coupon_pre_suffix = $data['coupon_pre_suffix'];
        $coupon_post_suffix = $data['coupon_post_suffix'];
        unset($data['coupon_number']);
        unset($data['coupon_number_symbol']);
        unset($data['coupon_pre_suffix']);
        unset($data['coupon_post_suffix']);
        $return_ids = array();
        for ($i = 1; $i <= $coupon_number; $i++) {
            $data['coupon_code'] = $this->_generate_coupone_code($coupon_number_symbol, $coupon_pre_suffix, $coupon_post_suffix);
            if (count($this->get('', array('coupon_code' => $data['coupon_code'])))) {
                $i--;
                continue;
            }
            $id = $this->add($data, $add_exist);
            $this->addProductsToCoupon($products, $id);
            $return_ids[] = $id;

        }
        return $return_ids;
    }

    /**Add coupon
     * @param $data
     * @return mixed
     */
    public function add($data, $add_exist = true)
    {
        if ($add_exist) {
            if (isset($data['for_products_id'])) {
                $data['for_products_id'] = implode(",", $data['for_products_id']);
            } else {
                $data['for_products_id'] = 0;
            }
            if (isset($data['for_categories_id'])) {
                $data['for_categories_id'] = implode(",", $data['for_categories_id']);
            } else {
                $data['for_categories_id'] = 0;
            }
            if (empty($data['coupon_start_date'])) {
                $data['coupon_start_date'] = '0000-00-00';
            } else {
                $data['coupon_start_date'] = date("Y-m-d", strtotime($data['coupon_start_date']));
            }
            if (empty($data['coupon_expire_date'])) {
                $data['coupon_expire_date'] = '0000-00-00';
            } else {
                $data['coupon_expire_date'] = date("Y-m-d", strtotime($data['coupon_expire_date']));
            }
        }
        $data['tax_id'] = 0;
        $data['used'] = 0;
        $data['for_user_id'] = 0;
        $data['not_for_old_price'] = 0;
        $data['not_for_different_prices'] = 0;
        $data['min_amount'] = 0;
        $data['for_users_id'] = 0;
        $data['for_user_groups_id'] = 0;
        $data['for_manufacturers_id'] = 0;
        $data['for_vendors_id'] = 0;
        $data['not_for_users_id'] = 0;
        $data['not_for_user_groups_id'] = 0;
        $data['not_for_products_id'] = 0;
        $data['not_for_categories_id'] = 0;
        $data['not_for_manufacturers_id'] = 0;
        $data['not_for_vendors_id'] = 0;
        $data['coupon_start_time'] = 0;
        $data['coupon_end_time'] = 23;
        $data['dateadded'] = date('Y-m-d H:i:s');
        $this->db->insert('tblcoupons', $data);
        $insert_id = $this->db->insert_id();
        if ($insert_id) {
            logActivity('New Coupon Added [CouponID: ' . $insert_id . ' CouponCode: ' . $data['coupon_code'] . ']');
            return $insert_id;
        }
        return false;
    }

    /**Add One coupone and group
     * @param $data
     * @return bool|mixed
     */
    public function addOneCoupon($data)
    {
        if (!count($this->get('', array('coupon_code' => $data['coupon_code'])))) {
            $products = $data['add_product_id'];
            unset($data['add_product_id']);
            unset($data['product_id']);
            unset($data['value_for_prod_id']);
            $group_id = $this->addGroup($data);
        }
        if ($data['coverage'] == '1') {
            $data['coupon_for_whole_order'] = 1;
        } else {
            $data['coupon_for_whole_order'] = 0;
        }
        unset($data['group_name']);
        unset($data['quantity_type']);
        unset($data['coverage']);
        if (isset($group_id) && $group_id) {
            $data['group_id'] = $group_id;
            $result = $this->add($data);
            if ($result) {
                $this->addProductsToCoupon($products, $result);
                $coupons = $this->get('', array('group_id' => $group_id));
                foreach ($coupons as $k => $coupon) {
                    $coupons[$k]['products_affix'] = $this->getProducts('', array('coupon_id' => $coupon['coupon_id']));
                }
                $this->sendToSite($coupons, SHOP_URL . 'add');
                return $result;
            }
        }
        return false;
    }

    /**Add list of products to coupon. Check if product_id and coupon_value is not empty.
     * @param $data
     * @param $coupon_id
     */
    public function addProductsToCoupon($data, $coupon_id)
    {
        if (count($data)) {
            foreach ($data as $product) {
                if($product['product_id'] != '' && $product['value'] !=''){
                    $this->db->insert('tblcoupons_affix', array('coupon_id' => $coupon_id, 'product_id' => $product['product_id'], 'value' => $product['value']));
                }
            }
        }
    }

    /**Add many coupons
     * @param $data
     * @return bool
     */
    public function addManyCoupon($data)
    {
        $data['coverage'] = $data['coverages'];
        unset($data['coverages']);
        $products = $data['add_product_id'];
        unset($data['add_product_id']);
        unset($data['product_id']);
        unset($data['value_for_prod_id']);
        $group_id = $this->addGroup($data);
        if ($data['coverage'] == '1') {
            $data['coupon_for_whole_order'] = 1;
        } else {
            $data['coupon_for_whole_order'] = 0;
        }
        unset($data['group_name']);
        unset($data['quantity_type']);
        unset($data['coverage']);
        if (isset($group_id) && $group_id) {
            $data['group_id'] = $group_id;
            $this->generate_coupons($data, $products);
            $coupons = $this->get('', array('group_id' => $group_id));
            foreach ($coupons as $k => $coupon) {
                $coupons[$k]['products_affix'] = $this->getProducts('', array('coupon_id' => $coupon['coupon_id']));
            }
            $this->sendToSite($coupons, SHOP_URL . 'add');
            return true;
        }
        return false;
    }

    /**Add coupons to exist group
     * @param $data
     * @return bool
     */
    public function addAdditionalCoupon($data)
    {
        $group = (array)$this->get_group($data['group_id']);
        $random_coupon = $this->get('', array('group_id' => ($data['group_id'])));
        $group['group_id'] = $data['group_id'];
        $group['coupon_number'] = $data['coupon_number'];;
        if ($group['coverage'] == '1') {
            $data['coupon_for_whole_order'] = 1;
        } else {
            $data['coupon_for_whole_order'] = 0;
        }
        unset($group['id']);
        unset($group['group_name']);
        unset($group['quantity_type']);
        unset($group['coverage']);
        $this->db->select('product_id, value');
        $this->db->where('coupon_id', $random_coupon[0]['coupon_id']);
        $products = $this->db->get('tblcoupons_affix')->result_array();
        $created_ids = $this->generate_coupons($group, $products, false);
        $created_ids = implode(",", $created_ids);
        $coupons = $this->get('', "coupon_id in ($created_ids)");
        foreach ($coupons as $k => $coupon) {
            $coupons[$k]['products_affix'] = $this->getProducts('', array('coupon_id' => $coupon['coupon_id']));
        }
        $this->sendToSite($coupons, SHOP_URL . 'add');
        $this->logCouponActivity($group['group_id'], 'coupons_group_added_additional_coupons', serialize($data));
        return true;
    }

    /**Add group for coupone
     * @param $data
     * @return mixed
     */
    public function addGroup($data)
    {
        unset($data['coupon_code']);
        if (isset($data['coupon_number'])) {
            unset($data['coupon_number']);
        }
        if ($data['coupon_type'] == '0' && $data['coupon_value'] >= 100) {
            return false;
        }
        if (isset($data['for_products_id'])) {
            $data['for_products_id'] = implode(",", $data['for_products_id']);
        } else {
            $data['for_products_id'] = 0;
        }
        if (isset($data['for_categories_id'])) {
            $data['for_categories_id'] = implode(",", $data['for_categories_id']);
        } else {
            $data['for_categories_id'] = 0;
        }
        if (empty($data['coupon_start_date'])) {
            $data['coupon_start_date'] = '0000-00-00';
        } else {
            $data['coupon_start_date'] = date("Y-m-d", strtotime($data['coupon_start_date']));
        }
        if (empty($data['coupon_expire_date'])) {
            $data['coupon_expire_date'] = '0000-00-00';
        } else {
            $data['coupon_expire_date'] = date("Y-m-d", strtotime($data['coupon_expire_date']));
        }
        $data['tax_id'] = 0;
        $data['used'] = 0;
        $data['for_user_id'] = 0;
        $data['not_for_old_price'] = 0;
        $data['not_for_different_prices'] = 0;
        $data['min_amount'] = 0;
        $data['for_users_id'] = 0;
        $data['for_user_groups_id'] = 0;
        $data['for_manufacturers_id'] = 0;
        $data['for_vendors_id'] = 0;
        $data['not_for_users_id'] = 0;
        $data['not_for_user_groups_id'] = 0;
        $data['not_for_products_id'] = 0;
        $data['not_for_categories_id'] = 0;
        $data['not_for_manufacturers_id'] = 0;
        $data['not_for_vendors_id'] = 0;
        $data['coupon_start_time'] = 0;
        $data['coupon_end_time'] = 23;
        $this->db->insert('tblcoupons_group', $data);
        $insert_id = $this->db->insert_id();
        if ($insert_id) {
            logActivity('New Coupon Group Added [GroupID: ' . $insert_id . ' GroupName: ' . $data['group_name'] . ']');
            $this->logCouponActivity($insert_id, 'coupons_group_added', serialize($data));
        }
        return $insert_id;
    }

    /**Delete coupon
     * @param $id
     * @return bool
     */
    public function delete($id)
    {
        $coupons = $this->get('', array('coupon_id' => $id));
        $this->sendToSite($coupons, SHOP_URL . 'delete');
        $this->db->where('coupon_id', $id);
        $this->db->delete('tblcoupons');
        if ($this->db->affected_rows() > 0) {
            logActivity('Coupon Deleted ID[' . $id . ']');
            return true;
        }
    }

    /**Delete coupon group and all coupons in this group
     * @param $id
     */
    public function deleteCouponGroup($id)
    {
        $this->db->where('id', $id);
        $this->db->delete('tblcoupons_group');
        if ($this->db->affected_rows() > 0) {
            logActivity('Deleted Coupon Group ID[' . $id . ']');
        }
        $coupons = $this->get('', array('group_id' => $id));
        $this->sendToSite($coupons, SHOP_URL . 'delete');
        $this->db->where('group_id', $id);
        $this->db->delete('tblcoupons');
        if ($this->db->affected_rows() > 0) {
            logActivity('Coupons Deleted');
        }
    }

    /**Send data to shop site
     * @param $data
     * @param $url
     */
    private function sendToSite($data, $url)
    {
        foreach ($data as $k => $v) {
            $data[$k]['for_categories_id'] = $this->getCategoryIn($v['for_categories_id']);
        }
        $data_string = json_encode($data);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'Content-Length: ' . strlen($data_string))
        );
        curl_setopt($ch, CURLOPT_USERPWD, 'apelsun:apelsun');
        curl_exec($ch);
        curl_close($ch);
    }

    /**Publish or unpublish coupon group and coupon code
     * @param $id
     * @param $status
     * @return bool
     */
    public function changeCouponStatus($id, $status)
    {
        $this->db->where('id', $id);
        $this->db->update('tblcoupons_group', array('coupon_publish' => $status));
        if ($this->db->affected_rows() > 0) {
            $this->db->where('group_id', $id);
            $this->db->update('tblcoupons', array('coupon_publish' => $status));
            $coupons = $this->get('', array('group_id' => $id));
            $this->sendToSite($coupons, SHOP_URL . 'update');
            $this->logCouponActivity($id, 'coupons_group_changed_status', serialize(array('coupon_publish' => $status)));
            return true;
        }
        return false;
    }

    /**Update group and coupons in this group
     * @param $data
     * @param $id
     * @return bool
     */
    public function updateGroup($data, $id)
    {
        if (isset($data['for_products_id'])) {
            $data['for_products_id'] = implode(",", $data['for_products_id']);
        } else {
            $data['for_products_id'] = 0;
        }
        if (isset($data['for_categories_id'])) {
            $data['for_categories_id'] = implode(",", $data['for_categories_id']);
        } else {
            $data['for_categories_id'] = 0;
        }
        if (empty($data['coupon_start_date'])) {
            $data['coupon_start_date'] = '0000-00-00';
        } else {
            $data['coupon_start_date'] = date("Y-m-d", strtotime($data['coupon_start_date']));
        }
        if (empty($data['coupon_expire_date'])) {
            $data['coupon_expire_date'] = '0000-00-00';
        } else {
            $data['coupon_expire_date'] = date("Y-m-d", strtotime($data['coupon_expire_date']));
        }
        if(isset($data['product_id'])) unset($data['product_id']);
        if(isset($data['value_for_prod_id'])) unset($data['value_for_prod_id']);
        $this->db->where('id', $id);
        $this->db->update('tblcoupons_group', $data);
        if ($this->db->affected_rows() > 0) {
            $this->logCouponActivity($id, 'coupons_group_changed', serialize($data));
            unset($data['group_name']);
            unset($data['coverage']);
            $this->db->where('group_id', $id);
            $this->db->update('tblcoupons', $data);
            $coupons = $this->get('', array('group_id' => $id));
            $this->sendToSite($coupons, SHOP_URL . 'update');
            return true;
        }
        return false;
    }

    /**Update one coupon
     * @param $data
     * @param $id
     */
    public function updateOneCoupon($data, $id)
    {
        $this->db->where('coupon_id', $id);
        $this->db->update('tblcoupons', $data);
        $coupons = $this->get('', array('coupon_id' => $id));
        $this->sendToSite($coupons, SHOP_URL . 'update');
    }

    /**Get group by coupon for order profile
     * @param $coupon_id
     * @return mixed
     */
    public function getGroupByCoupon($coupon_id)
    {
        if(is_numeric($coupon_id)){
            $this->db->join('tblcoupons_group', 'tblcoupons_group.id = tblcoupons.group_id', 'left');
            $this->db->where('coupon_id', $coupon_id);
            return $this->db->select('tblcoupons_group.*')->from('tblcoupons')->get()->row();
        }
        return false;

    }

    /**Add log activity for coupon group
     * @param $group_id
     * @param $description
     * @param string $additional_data
     * @return mixed
     */
    public function logCouponActivity($group_id, $description, $additional_data = '')
    {
        $log = array(
            'date' => date('Y-m-d H:i:s'),
            'description' => $description,
            'group_id' => $group_id,
            'staffid' => get_staff_user_id(),
            'additional_data' => $additional_data,
            'full_name' => get_staff_full_name(get_staff_user_id())
        );
        $this->db->insert('tblcouponslog', $log);
        return $this->db->insert_id();
    }

    /**Get log activities for coupon group
     * @param $id
     * @return mixed
     */
    public function getCouponActivityLog($id)
    {
        $sorting = do_action('coupon_activity_log_default_sort', 'ASC');

        $this->db->where('group_id', $id);
        $this->db->order_by('date', $sorting);

        return $this->db->get('tblcouponslog')->result_array();
    }

    /**Select all products wich consist coupon
     * @param $coupon_id
     * @return mixed
     */
    public function getProductsInCoupon($coupon_id)
    {
        $this->db->select('tblcoupons_affix.value,tblproducts.name,tblproducts.id,tblcoupons_affix.product_id');
        $this->db->where('coupon_id', $coupon_id);
        $this->db->join('tblproducts', 'tblproducts.product_key=tblcoupons_affix.product_id', 'left');
        return $this->db->get('tblcoupons_affix')->result_array();
    }

    public function addProductToCoupon($id, $data){
        $coupons = $this->get('', array('group_id' => $id));
        $data_to_shop = array();
        foreach ($coupons as $coupon){
            $this->addOneProductToCoupon($data,$coupon['coupon_id']);
            $data_to_shop[] = array('coupon_id' => $coupon['coupon_id'], 'product_id' => $data['product_id'], 'value' => $data['value_for_prod_id']);
        }
        $data_to_shop = (object) $data_to_shop;
        $this->sendToSiteProduct($data_to_shop,SHOP_URL . 'product_add');
        if($data['product_id'] != '' && $data['value_for_prod_id'] !='') {
            $this->logCouponActivity($id, 'coupons_added_new_product', serialize($data));
        }
        return true;
    }

    public function addOneProductToCoupon($data, $coupon_id)
    {
                if($data['product_id'] != '' && $data['value_for_prod_id'] !=''){
                    $product = array('coupon_id' => $coupon_id, 'product_id' => $data['product_id'], 'value' => $data['value_for_prod_id']);
                    $this->db->insert('tblcoupons_affix',$product);
                }
    }

    private function sendToSiteProduct($data, $url)
    {
        $data_string = json_encode($data);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'Content-Length: ' . strlen($data_string))
        );
        curl_setopt($ch, CURLOPT_USERPWD, 'apelsun:apelsun');
        curl_exec($ch);
        curl_close($ch);
    }

    public function deleteProductFromCoupon($id, $data){
        $coupons = $this->get('', array('group_id' => $id));
        $data_to_shop = array();
        foreach ($coupons as $coupon){
            $this->db->where('coupon_id', $coupon['coupon_id']);
            $this->db->where('product_id', $data['product_key']);
            $this->db->delete('tblcoupons_affix');
            $data_to_shop[] = array('coupon_id'=>$coupon['coupon_id'],'product_id'=>$data['product_key']);
        }
        $data_to_shop = (object) $data_to_shop;
        $this->sendToSiteProduct($data_to_shop,SHOP_URL . 'product_delete');
        $this->logCouponActivity($id, 'coupons_deleted_product', serialize($data));
        return true;
    }
}