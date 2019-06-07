<?php
if ( ! defined('BASEPATH')) exit('No direct script access allowed');
include APPPATH . 'third_party/phpmailer/PHPMailerAutoload.php';
include APPPATH . 'third_party/phpmailer/class.smtp.php';
class Estimate extends CI_Controller {
	
	public function __construct(){
        parent::__construct();
		$this->load->model(array('admin/User_model','api/api_model','admin/Client_model','admin/Estimate_model'));
		$this->load->library('email'); 
    }
	
	public function create_estimate()
	{
		$token = $this->input->get_request_header('Authorization');
		if(empty($token)){
			echo json_encode(array("status_code"=>0,"status_message"=>"Token empty"));
		}else{
			$response = $this->token_auth($token);
			if(empty($response)){
				echo json_encode(array("status_code"=>0,"status_message"=>"Token authentication failed"));
			}else{
				$_POST = json_decode(file_get_contents("php://input"), true);
				
				$estimate_data['user_id'] 			= $response->id;
				$estimate_data['estimate_id'] 		= $_POST['estimate_id'];
				$estimate_data['date'] 				= $_POST['date'];
				$estimate_data['po_number'] 		= $_POST['po_number'];
				$estimate_data['client_id'] 		= $_POST['client_id'];
				$estimate_data['item_id'] 			= '';
				$estimate_data['sub_total'] 		= $_POST['description']['subtotal'];
				$estimate_data['tax_amount'] 		= $_POST['description']['tax'];
				$estimate_data['total_price'] 		= $_POST['description']['total'];
				$estimate_data['accept_credit_card']= $_POST['payment_option']['accept_credit_cards'];
				$estimate_data['accept_echeck'] 	= $_POST['payment_option']['accept_echeck'];
				$estimate_data['client_signature'] 	= $_POST['contract_signatures']['show_client_signature'];
				$estimate_data['my_signature'] 		= $_POST['contract_signatures']['show_my_signature'];
				$estimate_data['client_note'] 		= $_POST['client_note'];
				$estimate_data['contract_file'] 	= '';
				$estimate_data['status'] 			= 'estimate';
				$estimate_data['rate_status'] 		= 'true';
				$estimate_data['quantity_status'] 	= 'true';
				$estimate_data['total_status'] 		= 'true';
				
				$estimate_id = $this->api_model->insert_estimate($estimate_data);
				
				if($estimate_id){
					
					/* Save estimate items */
					if(!empty($_POST['description']['items'])){
						$item_array = array();
						foreach($_POST['description']['items'] as $k=>$item_val)
						{
							$item_array[] = array(
								"estimate_id"=>$estimate_id,
								"item_id"=>$item_val['item_id'],
								"item_quantity"=>$item_val['item_quantity'],
								"item_total_price"=>$item_val['item_total_price']
							);
						}
						$item_response = $this->Estimate_model->insert_estimate_items($item_array);
					}
					
					/* insert scheduled payments of estmate */
					if(!empty($_POST['description']['payment_schedule'])){
						$payment_array = array();
						foreach($_POST['description']['payment_schedule'] as $key=>$val){
							
							$payment_array[$key]['estimate_id'] 	= $estimate_id;
							$payment_array[$key]['payment_type'] 	= $val['payment_type'];
							$payment_array[$key]['payment_name'] 	= $val['payment_name'];
							$payment_array[$key]['payment_amount']	= $val['value'];
						}
						$payment_response = $this->api_model->insert_estimate_payments($payment_array);
					}
					/* Save contract if total amount is > 3300 */
					if($_POST['description']['total'] >= 3300){
						$pdf_name = 'contract_'.time().'.pdf';
						$contract_data['contract_file']	= $pdf_name;
						$this->estimate_contract($estimate_id,$pdf_name,$response->id);
						$this->api_model->update_estimate_contract($estimate_id,$contract_data);
					}
					
					/* insert multiple images of estimate */
					if(!empty($_POST['photos'])){
						$esimatation_images = array();
						foreach($_POST['photos'] as $k=>$images){
							$image_base64 = base64_decode($images);
							$file_name = 'estimate_'.uniqid().'.png';
							$path = '/home/midrifur/public_html/reliableScapes/uploads/estimation/'.$file_name;
							file_put_contents($path, $image_base64);
							$esimatation_images[$k]['estimate_id'] = $estimate_id;
							$esimatation_images[$k]['image_name'] = $file_name;
						}
						$image_response = $this->api_model->insert_estimate_images($esimatation_images);
					}
					
				}
				
				echo json_encode(array("status_code"=>1,"status_message"=>"Estimate created successfully"));
			}
		}
	}
	
	public function get_estimate()
	{
		$token = $this->input->get_request_header('Authorization');
		if(empty($token)){
			echo json_encode(array("status_code"=>0,"status_message"=>"Token empty"));
		}else{
			$response = $this->token_auth($token);
			if(empty($response)){
				echo json_encode(array("status_code"=>0,"status_message"=>"Token authentication failed"));
			}else{
				$estimate_list = $this->api_model->get_estimates($response->id);
				
				if(!empty($estimate_list)){
					$estimates = array();
					$key = 0;
					foreach($estimate_list as $estimate){
						$estimates[$key]['id'] 				= $estimate['id'];
						$estimates[$key]['estimate_id'] 	= $estimate['estimate_id'];
						$estimates[$key]['date'] 			= $estimate['date'];
						$estimates[$key]['po_number'] 		= $estimate['po_number'];
						
						/* Show client data */
						$client_info = $this->Client_model->client_details($estimate['client_id']);
						$client = null;
						if(!empty($client_info)){
							$client['client_id'] 	= $client_info['client_id'];
							$client['basic_information']['name'] 		= $client_info['client_name'];
							$client['basic_information']['email_address']= $client_info['client_email'];
							$client['basic_information']['mobile'] 		= $client_info['phone_mobile'];
							$client['basic_information']['phone'] 		= $client_info['phone_other'];
							$client['billing_address']['address1'] 		= $client_info['address'];
							$client['billing_address']['address2'] 		= $client_info['additional_address'];
							$client['billing_address']['city'] 			= $client_info['city'];
							$client['billing_address']['state'] 		= $client_info['state'];
							$client['billing_address']['zip_code'] 		= $client_info['zipcode'];
							
							$client['service_address']['service_address1'] 	= $client_info['service_address_1'];
							$client['service_address']['service_address2'] 	= $client_info['service_address_2'];
							$client['service_address']['service_city'] 		= $client_info['service_city'];
							$client['service_address']['service_state'] 	= $client_info['service_state'];
							$client['service_address']['service_zip_code'] 	= $client_info['service_zipcode'];
							$client['private_notes'] 	= $client_info['private_notes'];
						}
						$estimates[$key]['client'] 		= $client;
						$estimates[$key]['contract_file']= ($estimate['contract_file']) ? base_url('assets/contract/').$estimate['contract_file']: '';
						
						/* item list of estimate */
						//$estimate_items = $this->Estimate_model->get_item_by_ids(explode(',',$estimate['item_id']));
						$estimate_items		= $this->Estimate_model->estimate_item_by_id($estimate['id']);
						
						$items = array();
						if(!empty($estimate_items)){
							$j = 0;
							foreach($estimate_items as $val){
						
								$items[$j]['item_id'] 	= $val['item_id'];
								$items[$j]['item_name'] = $val['item_name'];
								$items[$j]['rate'] 		= $val['item_rate'];
								$items[$j]['description'] = $val['notes'];
								$items[$j]['quantity'] = $val['item_quantity'];
								
								/* Items's tax info */
								$item_tax_list = $this->Client_model->all_taxes();
								$tax_list = array();
								$selected_tax_ids = explode(',',$val['tax_id']);
								if(!empty($item_tax_list)){
									foreach($item_tax_list as $k=>$tax){
										$tax_list[$k]['tax_id'] 	= $tax['tax_id'];
										$tax_list[$k]['tax_name'] 	= $tax['tax_name'];
										$tax_list[$k]['tax_amount'] = $tax['tax_amount'];
										if(in_array($tax['tax_id'],$selected_tax_ids)){
											$tax_list[$k]['enabled'] = true;
										}else{
											$tax_list[$k]['enabled'] = false;
										}
									}
								}
								$items[$j]['taxes']	= $tax_list;
								$j++;
							}							
						}
						
						$estimates[$key]['description']['items'] 	= $items;
						$estimates[$key]['description']['subtotal'] = $estimate['sub_total'];
						$estimates[$key]['description']['tax'] 		= $estimate['tax_amount'];
						$estimates[$key]['description']['total'] 	= $estimate['total_price'];
						$estimates[$key]['payment_option']['accept_credit_cards']= $estimate['accept_credit_card'];
						$estimates[$key]['payment_option']['accept_echeck'] 	= $estimate['accept_echeck'];
						$estimates[$key]['contract'] 		= $estimate['contract'];
						$estimates[$key]['client_note'] 	= $estimate['client_note'];
						$estimates[$key]['contract_signatures']['show_client_signature']= $estimate['client_signature'];
						$estimates[$key]['contract_signatures']['show_my_signature'] 	= $estimate['my_signature'];
						$estimates[$key]['description']['payment_schedule'] = $this->api_model->get_estimate_payments($estimate['id']);
						
						/* estimate images */
						$estimate_images = $this->api_model->get_estimate_images($estimate['id']);
						$images = array();
						if(!empty($estimate_images)){
							foreach($estimate_images as $img){
								$images[] = base_url('uploads/estimation/').$img['image_name'];
							}
						}
						$estimates[$key]['photos'] = $images;
						$estimates[$key]['rate_status'] = $estimate['rate_status'];
						$estimates[$key]['quantity_status'] = $estimate['quantity_status'];
						$estimates[$key]['total_status'] = $estimate['total_status'];
						$key++;
					}
					echo json_encode(array("status_code"=>1,"status_message"=>"Estimate List.","data"=>$estimates));
				}else{
					echo json_encode(array("status_code"=>1,"status_message"=>"No estimate found."));
				}
			}
		}
	}
	
	public function search_estimate()
	{
		$token = $this->input->get_request_header('Authorization');
		if(empty($token)){
			echo json_encode(array("status_code"=>0,"status_message"=>"Token empty"));
		}else{
			$response = $this->token_auth($token);
			if(empty($response)){
				echo json_encode(array("status_code"=>0,"status_message"=>"Token authentication failed"));
			}else{
				$_POST = json_decode(file_get_contents("php://input"), true);
				$keyword = $_POST['search_keyword'];
				$estimate_list = $this->api_model->get_search_estimates($response->id,$keyword);
				
				if(!empty($estimate_list)){
					$estimates = array();
					$key = 0;
					foreach($estimate_list as $estimate){
						$estimates[$key]['id'] 				= $estimate['id'];
						$estimates[$key]['estimate_id'] 	= $estimate['estimate_id'];
						$estimates[$key]['date'] 			= $estimate['date'];
						$estimates[$key]['po_number'] 		= $estimate['po_number'];
						
						/* Show client data */
						$client_info = $this->Client_model->client_details($estimate['client_id']);
						$client = null;
						if(!empty($client_info)){
							$client['client_id'] 	= $client_info['client_id'];
							$client['basic_information']['name'] 		= $client_info['client_name'];
							$client['basic_information']['email_address']= $client_info['client_email'];
							$client['basic_information']['mobile'] 		= $client_info['phone_mobile'];
							$client['basic_information']['phone'] 		= $client_info['phone_other'];
							$client['billing_address']['address1'] 		= $client_info['address'];
							$client['billing_address']['address2'] 		= $client_info['additional_address'];
							$client['billing_address']['city'] 			= $client_info['city'];
							$client['billing_address']['state'] 		= $client_info['state'];
							$client['billing_address']['zip_code'] 		= $client_info['zipcode'];
							
							$client['service_address']['service_address1'] 	= $client_info['service_address_1'];
							$client['service_address']['service_address2'] 	= $client_info['service_address_2'];
							$client['service_address']['service_city'] 		= $client_info['service_city'];
							$client['service_address']['service_state'] 	= $client_info['service_state'];
							$client['service_address']['service_zip_code'] 	= $client_info['service_zipcode'];
							$client['private_notes'] 	= $client_info['private_notes'];
						}
						$estimates[$key]['client'] 		= $client;
						$estimates[$key]['contract_file']= ($estimate['contract_file']) ? base_url('assets/contract/').$estimate['contract_file']: '';
						
						/* item list of estimate */
						//$estimate_items = $this->Estimate_model->get_item_by_ids(explode(',',$estimate['item_id']));
						$estimate_items		= $this->Estimate_model->estimate_item_by_id($estimate['id']);
						$items = array();
						if(!empty($estimate_items)){
							$j = 0;
							foreach($estimate_items as $val){
						
								$items[$j]['item_id'] 		= $val['item_id'];
								$items[$j]['item_name'] 	= $val['item_name'];
								$items[$j]['rate'] 			= $val['item_rate'];
								$items[$j]['description'] 	= $val['notes'];
								$items[$j]['quantity'] = $val['item_quantity'];
								
								/* Items's tax info */
								$item_tax_list = $this->Client_model->all_taxes();
								$tax_list = array();
								$selected_tax_ids = explode(',',$val['tax_id']);
								if(!empty($item_tax_list)){
									foreach($item_tax_list as $k=>$tax){
										$tax_list[$k]['tax_id'] 	= $tax['tax_id'];
										$tax_list[$k]['tax_name'] 	= $tax['tax_name'];
										$tax_list[$k]['tax_amount'] = $tax['tax_amount'];
										if(in_array($tax['tax_id'],$selected_tax_ids)){
											$tax_list[$k]['enabled'] = true;
										}else{
											$tax_list[$k]['enabled'] = false;
										}
									}
								}
								$items[$j]['taxes'] 		= $tax_list;
								$j++;
							}							
						}
						
						$estimates[$key]['description']['items'] 	= $items;
						$estimates[$key]['description']['subtotal'] = $estimate['sub_total'];
						$estimates[$key]['description']['tax'] 		= $estimate['tax_amount'];
						$estimates[$key]['description']['total'] 	= $estimate['total_price'];
						$estimates[$key]['payment_option']['accept_credit_cards']= $estimate['accept_credit_card'];
						$estimates[$key]['payment_option']['accept_echeck'] 	= $estimate['accept_echeck'];
						$estimates[$key]['contract'] 		= $estimate['contract'];
						$estimates[$key]['client_note'] 	= $estimate['client_note'];
						$estimates[$key]['contract_signatures']['show_client_signature']= $estimate['client_signature'];
						$estimates[$key]['contract_signatures']['show_my_signature'] 	= $estimate['my_signature'];
						$estimates[$key]['description']['payment_schedule'] = $this->api_model->get_estimate_payments($estimate['id']);
						
						/* estimate images */
						$estimate_images = $this->api_model->get_estimate_images($estimate['id']);
						$images = array();
						if(!empty($estimate_images)){
							foreach($estimate_images as $img){
								$images[] = base_url('uploads/estimation/').$img['image_name'];
							}
						}
						$estimates[$key]['photos'] = $images;
						$estimates[$key]['rate_status'] = $estimate['rate_status'];
						$estimates[$key]['quantity_status'] = $estimate['quantity_status'];
						$estimates[$key]['total_status'] = $estimate['total_status'];
						$key++;
					}
					echo json_encode(array("status_code"=>1,"status_message"=>"Estimate List.","data"=>$estimates));
				}else{
					echo json_encode(array("status_code"=>1,"status_message"=>"No estimate found."));
				}
			}
		}
	}
	
	public function edit_estimate()
	{
		$token = $this->input->get_request_header('Authorization');
		if(empty($token)){
			echo json_encode(array("status_code"=>0,"status_message"=>"Token empty"));
		}else{
			$response = $this->token_auth($token);
			if(empty($response)){
				echo json_encode(array("status_code"=>0,"status_message"=>"Token authentication failed"));
			}else{
				$_POST = json_decode(file_get_contents("php://input"), true);
				
				$estimate_data['estimate_id'] 		= $_POST['estimate_id'];
				$estimate_data['date'] 				= $_POST['date'];
				$estimate_data['po_number'] 		= $_POST['po_number'];
				$estimate_data['client_id'] 		= $_POST['client_id'];
				$estimate_data['item_id'] 			= '';
				$estimate_data['sub_total'] 		= $_POST['description']['subtotal'];
				$estimate_data['tax_amount'] 		= $_POST['description']['tax'];
				$estimate_data['total_price'] 		= $_POST['description']['total'];
				$estimate_data['accept_credit_card']= $_POST['payment_option']['accept_credit_cards'];
				$estimate_data['accept_echeck'] 	= $_POST['payment_option']['accept_echeck'];
				$estimate_data['client_signature'] 	= $_POST['contract_signatures']['show_client_signature'];
				$estimate_data['my_signature'] 		= $_POST['contract_signatures']['show_my_signature'];
				$estimate_data['client_note'] 		= $_POST['client_note'];
				$estimate_data['contract_file'] 	= '';
				
				$estimate_id = $this->api_model->update_estimate($_POST['id'],$estimate_data);
				/* Save estimate items */
				if(!empty($_POST['description']['items'])){
					$item_array = array();
					foreach($_POST['description']['items'] as $k=>$item_val)
					{
						$item_array[] = array(
							"estimate_id"=>$_POST['id'],
							"item_id"=>$item_val['item_id'],
							"item_quantity"=>$item_val['item_quantity'],
							"item_total_price"=>$item_val['item_total_price']
						);
					}
					
					$item_response = $this->Estimate_model->update_estimate_items($_POST['id'],$response->id,$item_array);
				}
				
				/* update payments */
				if(!empty($_POST['description']['payment_schedule'])){
					$payment_array = array();
					foreach($_POST['description']['payment_schedule'] as $key=>$val){
						
						$payment_array[$key]['estimate_id'] 	= $_POST['id'];
						$payment_array[$key]['payment_type'] 	= $val['payment_type'];
						$payment_array[$key]['payment_name'] 	= $val['payment_name'];
						$payment_array[$key]['payment_amount']	= $val['value'];
					}
					/* delete existing payments */
					$this->api_model->delete_estimate_payments($_POST['id']);
					$payment_response = $this->api_model->insert_estimate_payments($payment_array);
				}
				/* Save QBCC contract if total amount > 3300 */
				if($_POST['description']['total'] >= 3300){
					$pdf_name = 'contract_'.time().'.pdf';
					$contract_data['contract_file']	= $pdf_name;
					$this->estimate_contract($_POST['id'],$pdf_name,$response->id);
					$this->api_model->update_estimate_contract($_POST['id'],$contract_data);
				}
				/* updating estimate images */
				if(!empty($_POST['photos'])){
					$esimatation_images = array();
					foreach($_POST['photos'] as $k=>$images){
						$image_base64 = base64_decode($images);
						$file_name = 'estimate_'.uniqid().'.png';
						$path = '/home/midrifur/public_html/reliableScapes/uploads/estimation/'.$file_name;
						file_put_contents($path, $image_base64);
						$esimatation_images[$k]['estimate_id'] = $estimate_id;
						$esimatation_images[$k]['image_name'] = $file_name;
					}
					$this->api_model->delete_estimate_images($_POST['id']);
					$image_response = $this->api_model->insert_estimate_images($esimatation_images);
				}
				echo json_encode(array("status_code"=>1,"status_message"=>"Estimate updated successfully"));
			}
		}
	}
	
	public function estimate_contract($estimate_id,$pdf_name,$user_id)
	{
		
		/* $this->load->helper('pdf_helper');
		tcpdf();
		$obj_pdf = new TCPDF('P', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
		$obj_pdf->SetCreator(PDF_CREATOR);
		$title = "Invoice";
		$obj_pdf->SetTitle($title);
		
		$obj_pdf->SetHeaderData( '', 0, '', '');
		$obj_pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
		$obj_pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));
		$obj_pdf->SetDefaultMonospacedFont('helvetica');
		$obj_pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
		$obj_pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
		$obj_pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
		$obj_pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
		$obj_pdf->SetFont('helvetica', '', 9);
		$obj_pdf->setFontSubsetting(false);
		$obj_pdf->AddPage();
		ob_start();
		
		
		$contract_data['estimate_data'] = $this->api_model->estimate_data($estimate_id);
		$contract_data['contractor'] = $this->api_model->contractor_data($user_id);
		
		$this->load->library('parser');
		$content = $this->parser->parse('estimate/contract.php', $contract_data, TRUE);
		ob_end_clean();
		$obj_pdf->writeHTML($content, true, false, true, false, ''); 
		$files = $obj_pdf->Output($_SERVER['DOCUMENT_ROOT']. 'reliableScapes/assets/contract/'.$pdf_name, 'F'); */
		$contract_data['payment_data']	= $this->Estimate_model->estimate_payment_by_id($estimate_id);
		$contract_data['payments_array']	= $this->api_model->get_payments_app($estimate_id);
		$contract_data['estimate_data'] = $this->Estimate_model->estimate_data($estimate_id);
		$contract_data['contractor'] = $this->Estimate_model->contractor_data($user_id);
		//$content=$this->load->view('estimate/contract',$contract_data); 
		
		$this->load->library('parser');
		$content = $this->parser->parse('estimate/contract.php', $contract_data, TRUE);
		
		
		$html = $this->output->get_output();
		$this->load->library('pdf');
		$this->dompdf->loadHtml($content);
		$customPaper = array(0,0,830,810);
		$this->dompdf->set_paper($customPaper);
		$this->dompdf->render();
		$output = $this->dompdf->output();
		file_put_contents($_SERVER['DOCUMENT_ROOT'].'/reliableScapes/assets/contract/'.$pdf_name,$output);
	}
	
	public function generate_invoice()
	{
		$token = $this->input->get_request_header('Authorization');
		if(empty($token)){
			echo json_encode(array("status_code"=>0,"status_message"=>"Token empty"));
		}else{
			$response = $this->token_auth($token);
			if(empty($response)){
				echo json_encode(array("status_code"=>0,"status_message"=>"Token authentication failed"));
			}else{
				$_POST = json_decode(file_get_contents("php://input"), true);
				$this->form_validation->set_rules("estimate_id", "estimate id", "trim|required");
				if ($this->form_validation->run() == FALSE)
				{
					echo json_encode(array("status_code"=>0,"status_message"=>'All fields are required'));
				}else{
					$estimate_id = $_POST['estimate_id'];
					$user_id = $response->id;
					$response = $this->api_model->create_invoice($estimate_id,$user_id);
					if($response == 1){
						echo json_encode(array("status_code"=>1,"status_message"=>"Invoice has been created."));
					}else{
						echo json_encode(array("status_code"=>1,"status_message"=>"Invoice does not created."));
					}
				}
			}
		}
	}
	
	public function send_estimate()
	{
		$token = $this->input->get_request_header('Authorization');
		if(empty($token)){
			echo json_encode(array("status_code"=>0,"status_message"=>"Token empty"));
		}else{
			$response = $this->token_auth($token);
			if(empty($response)){
				echo json_encode(array("status_code"=>0,"status_message"=>"Token authentication failed"));
			}else{
				$_POST = json_decode(file_get_contents("php://input"), true);
				$this->form_validation->set_rules("email", "email", "trim|required");
				$this->form_validation->set_rules("estimate_id", "estimate id", "trim|required");
				if ($this->form_validation->run() == FALSE)
				{
					echo json_encode(array("status_code"=>0,"status_message"=>'All fields are required'));
				}else{
					
					$estimate_id = $_POST['estimate_id'];
					$email = $_POST['email'];
					$pagedata['estimate_details'] = $this->Estimate_model->estimate_data_by_id($estimate_id);
					/* $pagedata['item_data'] 		= $this->Estimate_model->estimate_item_by_id($estimate_id);
					$pagedata['payment_data'] 	= $this->Estimate_model->estimate_payment_by_id($estimate_id); */
					$pagedata['company_details']= $this->User_model->my_account($response->id);
					$pagedata['message']		= $_POST['message'];
					$pagedata['button_text']	= 'View Estimate';
					
					$this->load->library('parser');
					$email_content = $this->parser->parse('email/estimate_email_template', $pagedata, TRUE);
					
					$config = array(
						'charset'=>'utf-8',
						'wordwrap'=> TRUE,
						'mailtype' => 'html'
					);

					$mail = new PHPMailer;
					//$mail->SMTPDebug = 2;
					$mail->isSMTP();
					$mail->SMTPOptions = array(
						'ssl' => array(
							'verify_peer' => false,
							'verify_peer_name' => false,
							'allow_self_signed' => true
						)
					);
					$mail->Host = 'mail.midriffinfosolution.com';
					$mail->SMTPAuth = true;
					$mail->Username = 'test@midriffinfosolution.com';
					$mail->Password = '1DXb06-)$7Ut';
					$mail->SMTPSecure = 'tls';
					$mail->Port = 587;
					$mail->ClearAllRecipients();
					$mail->setFrom('test@midriffinfosolution.com', 'ReliableScapes');
					$mail->addAddress($email);
					$mail->isHTML(true);
					$mail->Subject = 'Estimate';
					$mail->Body    = $email_content;
					$mail->AltBody = '';
					if($mail->send()){
						echo json_encode(array("status_code"=>1,"status_message"=>"Estimate sent successfully."));
					}else{
						echo json_encode(array("status_code"=>1,"status_message"=>"Estimate sent unsuccessfully."));
					}
				}
			}
		}
	}
	
	public function estimate_view()
	{
		$token = $this->input->get_request_header('Authorization');
		if(empty($token)){
			echo json_encode(array("status_code"=>0,"status_message"=>"Token empty"));
		}else{
			$response = $this->token_auth($token);
			if(empty($response)){
				echo json_encode(array("status_code"=>0,"status_message"=>"Token authentication failed"));
			}else{
				$_POST = json_decode(file_get_contents("php://input"), true);
							
				$user_id = $response->id;
				$estimate_id = $_POST['estimate_id'];
				
				$contract_data['estimate_details'] = $this->Estimate_model->estimate_data_by_id($estimate_id);
				$contract_data['preferences_details'] = $this->User_model->get_preferences_details($user_id);
				
				$this->load->helper('pdf_helper');
				tcpdf();
				$obj_pdf = new TCPDF('P', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
				$obj_pdf->SetCreator(PDF_CREATOR);
				$title = ucwords($contract_data['estimate_details']['status']);
				$obj_pdf->SetTitle($title);
				
				$obj_pdf->SetHeaderData( '', 0, '', '');
				$obj_pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
				$obj_pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));
				$obj_pdf->SetDefaultMonospacedFont('helvetica');
				$obj_pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
				$obj_pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
				$obj_pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
				$obj_pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
				$obj_pdf->SetFont('helvetica', '', 9);
				$obj_pdf->setFontSubsetting(false);
				$obj_pdf->AddPage();
				ob_start();
				
				$this->load->library('parser');
				$content = $this->parser->parse('estimate/estimate_email_view', $contract_data, TRUE);
				ob_end_clean();
				$obj_pdf->writeHTML($content, true, false, true, false, '');
				$pdf_name = 'estimate-'.time().'.pdf';
				$files = $obj_pdf->Output($_SERVER['DOCUMENT_ROOT']. 'reliableScapes/assets/estimates/'.$pdf_name, 'F');
				echo json_encode(array("status_code"=>1,"status_message"=>"Estimate view.","data"=>base_url('assets/estimates/').$pdf_name));
				
			}
		}
	}
	
	public function delete_estimate()
	{
		$token = $this->input->get_request_header('Authorization');
		if(empty($token)){
			echo json_encode(array("status_code"=>0,"status_message"=>"Token empty"));
		}else{
			$response = $this->token_auth($token);
			if(empty($response)){
				echo json_encode(array("status_code"=>0,"status_message"=>"Token authentication failed"));
			}else{
				$_POST = json_decode(file_get_contents("php://input"), true);
				$this->form_validation->set_rules("estimate_id", "estimate id", "trim|required");
				if ($this->form_validation->run() == FALSE)
				{
					echo json_encode(array("status_code"=>0,"status_message"=>'All fields are required'));
				}else{
					$response = $this->Estimate_model->delete_estimate($_POST['estimate_id']);
					if(!empty($response)){
						foreach($response as $val){
							$image_name = $val['image_name'];
							$file='uploads/estimation/'.$image_name;
							unlink($file); 
						}
					}
					echo json_encode(array("status_code"=>1,"status_message"=>"Estimate deleted successfully."));
				}
			}
		}
	}
	
	public function create_invoice()
	{
		$token = $this->input->get_request_header('Authorization');
		if(empty($token)){
			echo json_encode(array("status_code"=>0,"status_message"=>"Token empty"));
		}else{
			$response = $this->token_auth($token);
			if(empty($response)){
				echo json_encode(array("status_code"=>0,"status_message"=>"Token authentication failed"));
			}else{
				$_POST = json_decode(file_get_contents("php://input"), true);
					
				$invoice_data['user_id'] 			= $response->id;
				$invoice_data['estimate_id'] 		= $_POST['estimate_id'];
				$invoice_data['date'] 				= $_POST['date'];
				$invoice_data['po_number'] 			= $_POST['po_number'];
				$invoice_data['client_id'] 			= $_POST['client_id'];
				$invoice_data['item_id'] 			= '';
				$invoice_data['sub_total'] 			= $_POST['description']['subtotal'];
				$invoice_data['tax_amount'] 		= $_POST['description']['tax'];
				$invoice_data['total_price'] 		= $_POST['description']['total'];
				$invoice_data['accept_credit_card']	= $_POST['payment_option']['accept_credit_cards'];
				$invoice_data['accept_echeck'] 		= $_POST['payment_option']['accept_echeck'];
				$invoice_data['client_signature'] 	= $_POST['contract_signatures']['show_client_signature'];
				$invoice_data['my_signature'] 		= $_POST['contract_signatures']['show_my_signature'];
				$invoice_data['client_note'] 		= $_POST['client_note'];
				$invoice_data['contract_file'] 		= '';
				$invoice_data['status'] 			= 'invoice';
				$invoice_data['payment_terms_days']	= $_POST['term_days'];
				
				$estimate_id = $this->api_model->insert_estimate($invoice_data);
				if($estimate_id){
					/* insert scheduled payments of estmate */
					if(!empty($_POST['description']['payment_schedule'])){
						$payment_array = array();
						foreach($_POST['description']['payment_schedule'] as $key=>$val){
							
							$payment_array[$key]['estimate_id'] 	= $estimate_id;
							$payment_array[$key]['payment_type'] 	= $val['payment_type'];
							$payment_array[$key]['payment_name'] 	= $val['payment_name'];
							$payment_array[$key]['payment_amount']	= $val['value'];
						}
						$payment_response = $this->api_model->insert_estimate_payments($payment_array);
					}
					
					/* Save estimate items */
					if(!empty($_POST['description']['items'])){
						$item_array = array();
						foreach($_POST['description']['items'] as $k=>$item_val)
						{
							$item_array[] = array(
								"estimate_id"=>$estimate_id,
								"item_id"=>$item_val['item_id'],
								"item_quantity"=>$item_val['item_quantity'],
								"item_total_price"=>$item_val['item_total_price']
							);
						}
						$item_response = $this->Estimate_model->insert_estimate_items($item_array);
					}
					/* Saved QBCC contract if amount > 3300 */
					if($_POST['description']['total'] >= 3300){
						$pdf_name = 'contract_'.time().'.pdf';
						$contract_data['contract_file']	= $pdf_name;
						$this->estimate_contract($estimate_id,$pdf_name,$response->id);
						$this->api_model->update_estimate_contract($estimate_id,$contract_data);
					}
					/* insert multiple images of estimate */
					if(!empty($_POST['photos'])){
						$esimatation_images = array();
						foreach($_POST['photos'] as $k=>$images){
							$image_base64 = base64_decode($images);
							$file_name = 'invoice_'.uniqid().'.png';
							$path = '/home/midrifur/public_html/reliableScapes/uploads/invoice/'.$file_name;
							file_put_contents($path, $image_base64);
							$esimatation_images[$k]['estimate_id'] = $estimate_id;
							$esimatation_images[$k]['image_name'] = $file_name;
						}
						$image_response = $this->api_model->insert_estimate_images($esimatation_images);
					}
					
				}
				
				echo json_encode(array("status_code"=>1,"status_message"=>"Invoice created successfully"));
			}
		}
	}
	
	public function edit_invoice()
	{
		$token = $this->input->get_request_header('Authorization');
		if(empty($token)){
			echo json_encode(array("status_code"=>0,"status_message"=>"Token empty"));
		}else{
			$response = $this->token_auth($token);
			if(empty($response)){
				echo json_encode(array("status_code"=>0,"status_message"=>"Token authentication failed"));
			}else{
				$_POST = json_decode(file_get_contents("php://input"), true);
				$this->form_validation->set_rules("client_id", "client id", "trim|required");
				
				$update_invoice['user_id'] 			= $response->id;
				$update_invoice['estimate_id'] 		= $_POST['estimate_id'];
				$update_invoice['date'] 			= $_POST['date'];
				$update_invoice['po_number'] 		= $_POST['po_number'];
				$update_invoice['client_id'] 		= $_POST['client_id'];
				$update_invoice['item_id'] 			= '';
				$update_invoice['sub_total'] 		= $_POST['description']['subtotal'];
				$update_invoice['tax_amount'] 		= $_POST['description']['tax'];
				$update_invoice['total_price'] 		= $_POST['description']['total'];
				$update_invoice['accept_credit_card']= $_POST['payment_option']['accept_credit_cards'];
				$update_invoice['accept_echeck'] 	= $_POST['payment_option']['accept_echeck'];
				$update_invoice['client_signature'] = $_POST['contract_signatures']['show_client_signature'];
				$update_invoice['my_signature'] 	= $_POST['contract_signatures']['show_my_signature'];
				$update_invoice['client_note'] 		= $_POST['client_note'];
				$update_invoice['status'] 			= 'invoice';
				$update_invoice['payment_terms_days']= $_POST['term_days'];
				$update_invoice['contract_file']	= "";
				
				$estimate_id = $this->api_model->update_estimate($_POST['invoice_id'],$update_invoice);
				if($estimate_id){
					/* Save estimate items */
					if(!empty($_POST['description']['items'])){
						$item_array = array();
						foreach($_POST['description']['items'] as $k=>$item_val)
						{
							$item_array[] = array(
								"estimate_id"=>$estimate_id,
								"item_id"=>$item_val['item_id'],
								"item_quantity"=>$item_val['item_quantity'],
								"item_total_price"=>$item_val['item_total_price']
							);
						}
						$item_response = $this->Estimate_model->insert_estimate_items($item_array);
					}
					
					/* insert scheduled payments of estmate */
					if(!empty($_POST['description']['payment_schedule'])){
						$payment_array = array();
						foreach($_POST['description']['payment_schedule'] as $key=>$val){
							
							$payment_array[$key]['estimate_id'] 	= $estimate_id;
							$payment_array[$key]['payment_type'] 	= $val['payment_type'];
							$payment_array[$key]['payment_name'] 	= $val['payment_name'];
							$payment_array[$key]['payment_amount']	= $val['value'];
						}
						$this->api_model->delete_estimate_payments($_POST['invoice_id']);
						$payment_response = $this->api_model->insert_estimate_payments($payment_array);
					}
					/* Saved QBCC contract if amount > 3300 */
					if($_POST['description']['total'] >= 3300){
						$pdf_name = 'contract_'.time().'.pdf';
						$contract_data['contract_file']	= $pdf_name;
						$this->estimate_contract($_POST['invoice_id'],$pdf_name,$response->id);
						$this->api_model->update_estimate_contract($estimate_id,$contract_data);
					}
					/* insert multiple images of estimate */
					if(!empty($_POST['photos'])){
						$esimatation_images = array();
						foreach($_POST['photos'] as $k=>$images){
							$image_base64 = base64_decode($images);
							$file_name = 'invoice_'.uniqid().'.png';
							$path = '/home/midrifur/public_html/reliableScapes/uploads/invoice/'.$file_name;
							file_put_contents($path, $image_base64);
							$esimatation_images[$k]['estimate_id'] = $estimate_id;
							$esimatation_images[$k]['image_name'] = $file_name;
						}
						$this->api_model->delete_estimate_images($_POST['invoice_id']);
						$image_response = $this->api_model->insert_estimate_images($esimatation_images);
					}
					
				}
				
				echo json_encode(array("status_code"=>1,"status_message"=>"Invoice updated successfully"));
			}
		}
	}
	
	public function get_invoice()
	{
		$token = $this->input->get_request_header('Authorization');
		if(empty($token)){
			echo json_encode(array("status_code"=>0,"status_message"=>"Token empty"));
		}else{
			$response = $this->token_auth($token);
			if(empty($response)){
				echo json_encode(array("status_code"=>0,"status_message"=>"Token authentication failed"));
			}else{
				$invoice_list = $this->api_model->get_invoice($response->id);
				
				if(!empty($invoice_list)){
					$invoices = array();
					$key = 0;
					foreach($invoice_list as $invoice){
						$invoices[$key]['id'] 				= $invoice['id'];
						$invoices[$key]['estimate_id'] 		= $invoice['estimate_id'];
						$invoices[$key]['date'] 			= $invoice['date'];
						$invoices[$key]['po_number'] 		= $invoice['po_number'];
						$invoices[$key]['term_days'] 		= $invoice['payment_terms_days'];
						
						/* Show client data */
						$client_info = $this->Client_model->client_details($invoice['client_id']);
						$client = null;
						if(!empty($client_info)){
							$client['client_id'] 	= $client_info['client_id'];
							$client['basic_information']['name'] 		= $client_info['client_name'];
							$client['basic_information']['email_address']= $client_info['client_email'];
							$client['basic_information']['mobile'] 		= $client_info['phone_mobile'];
							$client['basic_information']['phone'] 		= $client_info['phone_other'];
							$client['billing_address']['address1'] 		= $client_info['address'];
							$client['billing_address']['address2'] 		= $client_info['additional_address'];
							$client['billing_address']['city'] 			= $client_info['city'];
							$client['billing_address']['state'] 		= $client_info['state'];
							$client['billing_address']['zip_code'] 		= $client_info['zipcode'];
							
							$client['service_address']['service_address1'] 	= $client_info['service_address_1'];
							$client['service_address']['service_address2'] 	= $client_info['service_address_2'];
							$client['service_address']['service_city'] 		= $client_info['service_city'];
							$client['service_address']['service_state'] 	= $client_info['service_state'];
							$client['service_address']['service_zip_code'] 	= $client_info['service_zipcode'];
							$client['private_notes'] 	= $client_info['private_notes'];
						}
						$invoices[$key]['client'] 		= $client;
						$invoices[$key]['contract_file']= ($invoice['contract_file']) ? base_url('assets/contract/').$invoice['contract_file']: '';
						
						/* item list of invoice */
						//$estimate_items = $this->Estimate_model->get_item_by_ids(explode(',',$invoice['item_id']));
						$estimate_items		= $this->Estimate_model->estimate_item_by_id($invoice['id']);
						$items = array();
						if(!empty($estimate_items)){
							$j = 0;
							foreach($estimate_items as $val){
						
								$items[$j]['item_id'] 		= $val['item_id'];
								$items[$j]['item_name'] 	= $val['item_name'];
								$items[$j]['rate'] 			= $val['item_rate'];
								$items[$j]['description'] 	= $val['notes'];
								$items[$j]['quantity'] = $val['item_quantity'];
								
								/* Items's tax info */
								$item_tax_list = $this->Client_model->all_taxes();
								$tax_list = array();
								$selected_tax_ids = explode(',',$val['tax_id']);
								if(!empty($item_tax_list)){
									foreach($item_tax_list as $k=>$tax){
										$tax_list[$k]['tax_id'] 	= $tax['tax_id'];
										$tax_list[$k]['tax_name'] 	= $tax['tax_name'];
										$tax_list[$k]['tax_amount'] = $tax['tax_amount'];
										if(in_array($tax['tax_id'],$selected_tax_ids)){
											$tax_list[$k]['enabled'] = true;
										}else{
											$tax_list[$k]['enabled'] = false;
										}
									}
								}
								$items[$j]['taxes'] 		= $tax_list;
								$j++;
							}							
						}
						
						$invoices[$key]['description']['items'] 	= $items;
						$invoices[$key]['description']['subtotal'] = $invoice['sub_total'];
						$invoices[$key]['description']['tax'] 		= $invoice['tax_amount'];
						$invoices[$key]['description']['total'] 	= $invoice['total_price'];
						$invoices[$key]['payment_option']['accept_credit_cards']= $invoice['accept_credit_card'];
						$invoices[$key]['payment_option']['accept_echeck'] 	= $invoice['accept_echeck'];
						$invoices[$key]['contract'] 		= $invoice['contract'];
						$invoices[$key]['client_note'] 	= $invoice['client_note'];
						$invoices[$key]['contract_signatures']['show_client_signature']= $invoice['client_signature'];
						$invoices[$key]['contract_signatures']['show_my_signature'] 	= $invoice['my_signature'];
						$invoices[$key]['description']['payment_schedule'] = $this->api_model->get_estimate_payments($invoice['id']);
						
						/* invoice images */
						$estimate_images = $this->api_model->get_estimate_images($invoice['id']);
						$images = array();
						if(!empty($estimate_images)){
							foreach($estimate_images as $img){
								$images[] = base_url('uploads/invoice/').$img['image_name'];
							}
						}
						$invoices[$key]['photos'] = $images;
						$invoices[$key]['rate_status'] = $invoice['rate_status'];
						$invoices[$key]['quantity_status'] = $invoice['quantity_status'];
						$invoices[$key]['total_status'] = $invoice['total_status'];
						$key++;
					}
					echo json_encode(array("status_code"=>1,"status_message"=>"Invoice List.","data"=>$invoices));
				}else{
					echo json_encode(array("status_code"=>1,"status_message"=>"No invoice found."));
				}
			}
		}
	}
	
	public function get_search_invoice()
	{
		$token = $this->input->get_request_header('Authorization');
		if(empty($token)){
			echo json_encode(array("status_code"=>0,"status_message"=>"Token empty"));
		}else{
			$response = $this->token_auth($token);
			if(empty($response)){
				echo json_encode(array("status_code"=>0,"status_message"=>"Token authentication failed"));
			}else{
				$_POST = json_decode(file_get_contents("php://input"), true);
				$keyword = $_POST['search_keyword'];
				$invoice_list = $this->api_model->search_invoice($response->id,$keyword);
				
				if(!empty($invoice_list)){
					$invoices = array();
					$key = 0;
					foreach($invoice_list as $invoice){
						$invoices[$key]['id'] 				= $invoice['id'];
						$invoices[$key]['estimate_id'] 		= $invoice['estimate_id'];
						$invoices[$key]['date'] 			= $invoice['date'];
						$invoices[$key]['po_number'] 		= $invoice['po_number'];
						$invoices[$key]['term_days'] 		= $invoice['payment_terms_days'];
						
						/* Show client data */
						$client_info = $this->Client_model->client_details($invoice['client_id']);
						$client = null;
						if(!empty($client_info)){
							$client['client_id'] 	= $client_info['client_id'];
							$client['basic_information']['name'] 		= $client_info['client_name'];
							$client['basic_information']['email_address']= $client_info['client_email'];
							$client['basic_information']['mobile'] 		= $client_info['phone_mobile'];
							$client['basic_information']['phone'] 		= $client_info['phone_other'];
							$client['billing_address']['address1'] 		= $client_info['address'];
							$client['billing_address']['address2'] 		= $client_info['additional_address'];
							$client['billing_address']['city'] 			= $client_info['city'];
							$client['billing_address']['state'] 		= $client_info['state'];
							$client['billing_address']['zip_code'] 		= $client_info['zipcode'];
							
							$client['service_address']['service_address1'] 	= $client_info['service_address_1'];
							$client['service_address']['service_address2'] 	= $client_info['service_address_2'];
							$client['service_address']['service_city'] 		= $client_info['service_city'];
							$client['service_address']['service_state'] 	= $client_info['service_state'];
							$client['service_address']['service_zip_code'] 	= $client_info['service_zipcode'];
							$client['private_notes'] 	= $client_info['private_notes'];
						}
						$invoices[$key]['client'] 		= $client;
						$invoices[$key]['contract_file']= ($invoice['contract_file']) ? base_url('assets/contract/').$invoice['contract_file']: '';
						
						/* item list of invoice */
						//$estimate_items = $this->Estimate_model->get_item_by_ids(explode(',',$invoice['item_id']));
						$estimate_items		= $this->Estimate_model->estimate_item_by_id($invoice['id']);
						$items = array();
						if(!empty($estimate_items)){
							$j = 0;
							foreach($estimate_items as $val){
						
								$items[$j]['item_id'] 		= $val['item_id'];
								$items[$j]['item_name'] 	= $val['item_name'];
								$items[$j]['rate'] 			= $val['item_rate'];
								$items[$j]['description'] 	= $val['notes'];
								$items[$j]['quantity'] = $val['item_quantity'];
								
								/* Items's tax info */
								$item_tax_list = $this->Client_model->all_taxes();
								$tax_list = array();
								$selected_tax_ids = explode(',',$val['tax_id']);
								if(!empty($item_tax_list)){
									foreach($item_tax_list as $k=>$tax){
										$tax_list[$k]['tax_id'] 	= $tax['tax_id'];
										$tax_list[$k]['tax_name'] 	= $tax['tax_name'];
										$tax_list[$k]['tax_amount'] = $tax['tax_amount'];
										if(in_array($tax['tax_id'],$selected_tax_ids)){
											$tax_list[$k]['enabled'] = true;
										}else{
											$tax_list[$k]['enabled'] = false;
										}
									}
								}
								$items[$j]['taxes'] 		= $tax_list;
								$j++;
							}							
						}
						
						$invoices[$key]['description']['items'] 	= $items;
						$invoices[$key]['description']['subtotal'] = $invoice['sub_total'];
						$invoices[$key]['description']['tax'] 		= $invoice['tax_amount'];
						$invoices[$key]['description']['total'] 	= $invoice['total_price'];
						$invoices[$key]['payment_option']['accept_credit_cards']= $invoice['accept_credit_card'];
						$invoices[$key]['payment_option']['accept_echeck'] 	= $invoice['accept_echeck'];
						$invoices[$key]['contract'] 		= $invoice['contract'];
						$invoices[$key]['client_note'] 	= $invoice['client_note'];
						$invoices[$key]['contract_signatures']['show_client_signature']= $invoice['client_signature'];
						$invoices[$key]['contract_signatures']['show_my_signature'] 	= $invoice['my_signature'];
						$invoices[$key]['description']['payment_schedule'] = $this->api_model->get_estimate_payments($invoice['id']);
						
						/* invoice images */
						$estimate_images = $this->api_model->get_estimate_images($invoice['id']);
						$images = array();
						if(!empty($estimate_images)){
							foreach($estimate_images as $img){
								$images[] = base_url('uploads/invoice/').$img['image_name'];
							}
						}
						$invoices[$key]['photos'] = $images;
						$invoices[$key]['rate_status'] = $invoice['rate_status'];
						$invoices[$key]['quantity_status'] = $invoice['quantity_status'];
						$invoices[$key]['total_status'] = $invoice['total_status'];
						$key++;
					}
					echo json_encode(array("status_code"=>1,"status_message"=>"Invoice List.","data"=>$invoices));
				}else{
					echo json_encode(array("status_code"=>1,"status_message"=>"No invoice found."));
				}
			}
		}
	}
	
	public function qbcc_contract()
	{
		$data['estimate_data'] = $this->api_model->estimate_data();
		$data['contractor'] = $this->api_model->contractor_data();
		
		$this->load->view('estimate/contract', $data);
	}
	
	public function send_invoice()
	{
		$token = $this->input->get_request_header('Authorization');
		if(empty($token)){
			echo json_encode(array("status_code"=>0,"status_message"=>"Token empty"));
		}else{
			$response = $this->token_auth($token);
			if(empty($response)){
				echo json_encode(array("status_code"=>0,"status_message"=>"Token authentication failed"));
			}else{
				$_POST = json_decode(file_get_contents("php://input"), true);
				
				$invoice_id = $_POST['invoice_id'];
				$email = $_POST['email'];
				$pagedata['estimate_details'] = $this->Estimate_model->estimate_data_by_id($invoice_id);
				/* $pagedata['item_data'] 		= $this->Estimate_model->estimate_item_by_id($estimate_id);
				$pagedata['payment_data'] 	= $this->Estimate_model->estimate_payment_by_id($estimate_id); */
				$pagedata['company_details']= $this->User_model->my_account($response->id);
				$pagedata['message']		= $_POST['message'];
				$pagedata['button_text']	= 'View Invoice';
				
				$this->load->library('parser');
				$email_content = $this->parser->parse('email/estimate_email_template', $pagedata, TRUE);
				
				$config = array(
					'charset'=>'utf-8',
					'wordwrap'=> TRUE,
					'mailtype' => 'html'
				);

				$mail = new PHPMailer;
				//$mail->SMTPDebug = 2;
				$mail->isSMTP();
				$mail->SMTPOptions = array(
					'ssl' => array(
						'verify_peer' => false,
						'verify_peer_name' => false,
						'allow_self_signed' => true
					)
				);
				$mail->Host = 'mail.midriffinfosolution.com';
				$mail->SMTPAuth = true;
				$mail->Username = 'test@midriffinfosolution.com';
				$mail->Password = '1DXb06-)$7Ut';
				$mail->SMTPSecure = 'tls';
				$mail->Port = 587;
				$mail->ClearAllRecipients();
				$mail->setFrom('test@midriffinfosolution.com', 'ReliableScapes');
				$mail->addAddress($email);
				$mail->isHTML(true);
				$mail->Subject = 'Invoice';
				$mail->Body    = $email_content;
				$mail->AltBody = '';
				if($mail->send()){
					echo json_encode(array("status_code"=>1,"status_message"=>"Invoice sent successfully."));
				}else{
					echo json_encode(array("status_code"=>1,"status_message"=>"Invoice sent unsuccessfully."));
				}
			}
		}
	}
	
	public function test_contract()
	{
		$this->load->helper('pdf_helper');
		tcpdf();
		$obj_pdf = new TCPDF('P', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
		$obj_pdf->SetCreator(PDF_CREATOR);
		$title = "Invoice";
		$obj_pdf->SetTitle($title);
		
		$obj_pdf->SetHeaderData( '', 0, '', '');
		$obj_pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
		$obj_pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));
		$obj_pdf->SetDefaultMonospacedFont('helvetica');
		$obj_pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
		$obj_pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
		$obj_pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
		$obj_pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
		$obj_pdf->SetFont('helvetica', '', 9);
		$obj_pdf->setFontSubsetting(false);
		$obj_pdf->AddPage();
		ob_start();
		
		$this->load->library('parser');
		$content = $this->parser->parse('email/pdf_view.php', TRUE);
		ob_end_clean();
		$obj_pdf->writeHTML($content, true, false, true, false, ''); 
		$files = $obj_pdf->Output('file.pdf', 'I');
	}
	
	public function all_contract()
	{
		$token = $this->input->get_request_header('Authorization');
		if(empty($token)){
			echo json_encode(array("status_code"=>0,"status_message"=>"Token empty"));
		}else{
			$response = $this->token_auth($token);
			if(empty($response)){
				echo json_encode(array("status_code"=>0,"status_message"=>"Token authentication failed"));
			}else{
				$contracts_list = $this->User_model->get_all_contract($response->id);
				
				$all_contracts = array();
				if(!empty($contracts_list)){
					$i = 0;
					foreach($contracts_list as $contracts){
						$client_info = $this->Client_model->client_details($contracts['client_id']);
						$all_contracts[$i]['contract_id'] = $contracts['estimate_id'];
						$all_contracts[$i]['client_name'] = $client_info['client_name'];
						$all_contracts[$i]['contract_date'] = $contracts['date'];
						$all_contracts[$i]['type'] = $contracts['status'];
						$all_contracts[$i]['contract_file'] = base_url('assets/contract/').$contracts['contract_file'];
						$i++;
					}
					echo json_encode(array("status_code"=>1,"status_message"=>"Contract List.","data"=>$all_contracts));
				}else{
					echo json_encode(array("status_code"=>1,"status_message"=>"No contract found."));
				}
			}
		}
	}
	
	public function display_option()
	{
		$token = $this->input->get_request_header('Authorization');
		if(empty($token)){
			echo json_encode(array("status_code"=>0,"status_message"=>"Token empty"));
		}else{
			$response = $this->token_auth($token);
			if(empty($response)){
				echo json_encode(array("status_code"=>0,"status_message"=>"Token authentication failed"));
			}else{
				$_POST = json_decode(file_get_contents("php://input"), true);
				
				$display_data_array=array(
					'rate_status'		=> $_POST['rate_status'],
					'quantity_status'	=> $_POST['quantity_status'],
					'total_status'		=> $_POST['total_status']
				);
				
				$this->api_model->update_estimate($_POST['estimate_id'],$display_data_array);
				echo json_encode(array("status_code"=>1,"status_message"=>"Estimate updated."));
			}
		}
	}
	
	public function view_detail()
	{
		$token = $this->input->get_request_header('Authorization');
		if(empty($token)){
			echo json_encode(array("status_code"=>0,"status_message"=>"Token empty"));
		}else{
			$response = $this->token_auth($token);
			if(empty($response)){
				echo json_encode(array("status_code"=>0,"status_message"=>"Token authentication failed"));
			}else{
				$_POST = json_decode(file_get_contents("php://input"), true);
				$estimate = $this->Estimate_model->estimate_data_by_id($_POST['id']);
				
				$estimates['id'] 			= $estimate['id'];
				$estimates['estimate_id'] 	= $estimate['estimate_id'];
				$estimates['date'] 			= $estimate['date'];
				$estimates['po_number'] 	= $estimate['po_number'];
				$estimates['type'] 			= $estimate['status'];
				
				/* Show client data */
				$client_info = $this->Client_model->client_details($estimate['client_id']);
				$client = null;
				if(!empty($client_info)){
					$client['client_id'] 	= $client_info['client_id'];
					$client['basic_information']['name'] 		= $client_info['client_name'];
					$client['basic_information']['email_address']= $client_info['client_email'];
					$client['basic_information']['mobile'] 		= $client_info['phone_mobile'];
					$client['basic_information']['phone'] 		= $client_info['phone_other'];
					$client['billing_address']['address1'] 		= $client_info['address'];
					$client['billing_address']['address2'] 		= $client_info['additional_address'];
					$client['billing_address']['city'] 			= $client_info['city'];
					$client['billing_address']['state'] 		= $client_info['state'];
					$client['billing_address']['zip_code'] 		= $client_info['zipcode'];
					
					$client['service_address']['service_address1'] 	= $client_info['service_address_1'];
					$client['service_address']['service_address2'] 	= $client_info['service_address_2'];
					$client['service_address']['service_city'] 		= $client_info['service_city'];
					$client['service_address']['service_state'] 	= $client_info['service_state'];
					$client['service_address']['service_zip_code'] 	= $client_info['service_zipcode'];
					$client['private_notes'] 	= $client_info['private_notes'];
				}
				$estimates['client'] 		= $client;
				$estimates['contract_file']= ($estimate['contract_file']) ? base_url('assets/contract/').$estimate['contract_file']: '';
				
				/* item list of estimate */
				//$estimate_items = $this->Estimate_model->get_item_by_ids(explode(',',$estimate['item_id']));
				$estimate_items		= $this->Estimate_model->estimate_item_by_id($estimate['id']);
				
				$items = array();
				if(!empty($estimate_items)){
					$j = 0;
					foreach($estimate_items as $val){
				
						$items[$j]['item_id'] 	= $val['item_id'];
						$items[$j]['item_name'] = $val['item_name'];
						$items[$j]['rate'] 		= $val['item_rate'];
						$items[$j]['description'] = $val['notes'];
						$items[$j]['quantity'] = $val['item_quantity'];
						
						/* Items's tax info */
						$item_tax_list = $this->Client_model->all_taxes();
						$tax_list = array();
						$selected_tax_ids = explode(',',$val['tax_id']);
						if(!empty($item_tax_list)){
							foreach($item_tax_list as $k=>$tax){
								$tax_list[$k]['tax_id'] 	= $tax['tax_id'];
								$tax_list[$k]['tax_name'] 	= $tax['tax_name'];
								$tax_list[$k]['tax_amount'] = $tax['tax_amount'];
								if(in_array($tax['tax_id'],$selected_tax_ids)){
									$tax_list[$k]['enabled'] = true;
								}else{
									$tax_list[$k]['enabled'] = false;
								}
							}
						}
						$items[$j]['taxes']	= $tax_list;
						$j++;
					}							
				}
				
				$estimates['description']['items'] 	= $items;
				$estimates['description']['subtotal'] = $estimate['sub_total'];
				$estimates['description']['tax'] 		= $estimate['tax_amount'];
				$estimates['description']['total'] 	= $estimate['total_price'];
				$estimates['payment_option']['accept_credit_cards']= $estimate['accept_credit_card'];
				$estimates['payment_option']['accept_echeck'] 	= $estimate['accept_echeck'];
				$estimates['contract'] 		= $estimate['contract'];
				$estimates['client_note'] 	= $estimate['client_note'];
				$estimates['contract_signatures']['show_client_signature']= $estimate['client_signature'];
				$estimates['contract_signatures']['show_my_signature'] 	= $estimate['my_signature'];
				$estimates['description']['payment_schedule'] = $this->api_model->get_estimate_payments($estimate['id']);
				
				/* estimate images */
				$estimate_images = $this->api_model->get_estimate_images($estimate['id']);
				$images = array();
				if(!empty($estimate_images)){
					foreach($estimate_images as $img){
						$images[] = base_url('uploads/estimation/').$img['image_name'];
					}
				}
				$estimates['photos'] = $images;
				$estimates['rate_status'] = $estimate['rate_status'];
				$estimates['quantity_status'] = $estimate['quantity_status'];
				$estimates['total_status'] = $estimate['total_status'];
				echo json_encode(array("status_code"=>1,"status_message"=>"Estimate updated.","data"=>$estimates));
			}
		}
	}
	
	function token_auth($token)
	{
		return $this->api_model->check_token($token);
	}
}
?>