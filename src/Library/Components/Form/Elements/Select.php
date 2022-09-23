<?php
namespace Incodiy\Codiy\Library\Components\Form\Elements;

/**
 * Created on 19 Mar 2021
 * Time Created	: 03:17:34
 *
 * @filesource	Select.php
 *
 * @author		wisnuwidi@gmail.com - 2021
 * @copyright	wisnuwidi
 * @email		wisnuwidi@gmail.com
 */
 
trait Select {
	/**
	 * Create Input Selectbox
	 *
	 * @param string $name
	 * @param array $values
	 * @param boolean $selected
	 * @param array $attributes
	 * @param boolean $label
	 * @param array|bool $set_first_value
	 * 		: if !false = [null => 'Select All'] or you can set the other array value
	 */
	public function selectbox($name, $values = [], $selected = false, $attributes = [], $label = true, $set_first_value = [null => '']) {
		$attributes   = diy_form_change_input_attribute($attributes, 'class', 'chosen-select-deselect chosen-selectbox');		
		$optionValues = $set_first_value;
		
		if (isset($values[0]) && true === empty($values[0])) {
			if (!empty($set_first_value)) unset($values[0]);
		}
		
		if (false !== $set_first_value) {
			foreach ($values as $key => $value) {
				if (!empty($value)) $optionValues[$key] = $value;
			}
			$values = $optionValues;
		}
		
		$this->setParams('select', $name, $values, $attributes, $label, $selected);
		$this->inputDraw('select', $name);
	}
	
	/**
	 * Create Input Month
	 *
	 * @param string $name
	 * @param string $value
	 * @param array $attributes
	 * @param boolean $label
	 */
	public function month($name, $value = null, $attributes = [], $label = true) {
		$attributes = diy_form_change_input_attribute($attributes, 'class', 'chosen-select-deselect chosen-selectbox');
		
		$this->setParams('selectMonth', $name, $value, $attributes, $label);
		$this->inputDraw('selectMonth', $name);
	}
}