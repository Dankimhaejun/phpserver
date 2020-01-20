<?php

namespace App\Http\Controllers\Library;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Validator;
use DB;
use Curl;
use Auth;
use SoapBox\Formatter\Formatter;
use Htmldom;

use App\Book;
use App\Review;
use App\BookRead;
use App\BookBad;
use App\TimeMaximum;

class BookController extends Controller
{
    public function __construct()
    {
		//DB::connection()->enableQueryLog();
		//$queries = DB::getQueryLog();
		//var_dump($queries);
		//exit;
    }

	public function index(Request $request)
	{
	}

	public function show(Request $request, $book_id)
	{

		$company_id = $request->input('company_id', 1);
		$Timemaximum = new \App\TimeMaximum;
		$book = Book::findOrFail($book_id);
		$book->book_img = $book->book_img.'?type=m5';
		$book->count;
		$book->feelings;
		$book->tags->pluck('tag');
		// $book->library = \App\LibraryBook::where('book_id', $book_id)->with('location_config')->first();

		/**
		$book->chart = DB::select("
		SELECT

		");
		*/
		$tableName = ($company_id <= 2) ? "fly_library_book" : "fly_library_book_company{$company_id}";
		/**
		$book->library = DB::select("
		SELECT
			id, book_id, title, author, publisher, search_code, isbn, location_config_id
		FROM
			fly_book a
		LEFT JOIN
			{$tableName} b
		WHERE
			a.id = :book_id
		",['book_id' => $book_id])[0];


		$book->library->location_config = DB::select("
		SELECT
			text
		FROM
			fly_library_location_config
		WHERE
			id = :id
		",['id' => $book->library->location_config_id])[0];
		*/
		$book->library = DB::select("
		SELECT
			b.id, a.id as book_id, a.title, a.author, a.publisher, IFNULL(b.search_code, '-') AS search_code, a.isbn, b.location_config_id, IF(ISNULL(b.search_code), false, true) AS is_collected_book, c.minAge, c.maxAge
		FROM
			fly_book a
		LEFT JOIN
			{$tableName} b
		ON
			a.id = b.book_id
		LEFT JOIN
			ibookcareBookInfo c
		ON
			a.id = c.bookID
		WHERE
			a.id = :book_id
		",['book_id' => $book_id])[0];

		if (!empty($book->library->location_config_id)) {
			$book->library->location_config = DB::select("
			SELECT
				text
			FROM
				fly_library_location_config
			WHERE
				id = :id
			",['id' => $book->library->location_config_id])[0];
		} else {
			$book->library->location_config = (object)['text'=>'-'];
		}

		// print_r($book->library);
		// print_r(json_decode(json_encode($book->library),true));
		/*
		Array
		(
			[id] => 79571
			[book_id] => 70018
			[lib_code] => EM0000065227
			[title] => 5분 : 세상을 마주하는 시간
			[author] => 김진혁 지음.
			[publisher] => 문학동네
			[pubdate] => 2015
			[search_code] => 340.4-김78ㅇ
			[isbn] => 9788954636414
			[location_config_id] => 7
			[created_at] => 2017-10-23 19:27:14
			[updated_at] => 2017-10-24 11:34:23
			[location_config] => Array
				(
					[id] => 7
					[company_id] => 1
					[text] => [반포]종합자료실2(4층)
				)

		)
		*/


		$book->reviews = Review::with('member')->where('book_id', $book_id)->where('show_state', 'A')->orderBy('id', 'desc')->take(10)->get();

		foreach($book->reviews as $review) {
			$review->created_text = $Timemaximum->calculateTime($review->created_at);
			$review->content = nl2br($review->content);

			if($review->member->profile_img == '/images/img_temp01.png') {
				$review->member->profile_img = '';
			}
			else {
				if(strpos($review->member->profile_img, env('IMAGES_RESIZE_URL')) === false) {
					$review->member->profile_img = env('IMAGES_RESIZE_URL').'/member/'.$review->member->profile_img.'?width=86&height=86';
				}
			}
		}

		return response()->json($book);
	}

	public function isLoan(Request $request, $book_id) {
		$company_id = $request->input('company_id', 1);
		$book = Book::findOrFail($book_id);

		if ($company_id == 1) {
            $curl_handle = curl_init ("http://www.seocholib.or.kr/dls_le/index.php?mod=wdDataSearch&act=searchIList&deSearch=1&serSec=detail&item=title&word=&cond=and&item1=author&word1=&cond1=and&item2=publisher&word2=&cond2=and&item3=keyword&word3=&cond3=and&isbn=".$book->isbn."&dataType%5B%5D=book&manageCode=AA&placeCode=&pubYearS=&pubYearE=&callNo=&regNo=&dataSort=");
			curl_setopt ($curl_handle, CURLOPT_RETURNTRANSFER, true);
			$output = curl_exec ($curl_handle);

			$loan_state = '';
			$html = new Htmldom;
			$html->str_get_html($output);

			foreach($html->find('ol') as $element) {
				foreach($element->find('a') as $item) {
                    if (isset($item->nodes[0])){
                        if($item->nodes[0]->outertext == '예약가능') {
    						$loan_state = '대출중';
    					}
                    }
				}
				foreach($element->find('strong') as $item) {
					if($item->nodes[0]->outertext == '대출가능') {
						$loan_state = '대출가능';
					}
				}
			}
		} else if (preg_match("/^(?:9|10)$/", $company_id)) {
			$dbh = DB::connection('mysql_noprefix');
			$data = $dbh->select("
			SELECT
				lib_code, title
			FROM
				fly_library_book_company{$company_id}
			WHERE
				book_id = :book_id
			LIMIT 1
			", ['book_id' => $book_id])[0];
			$output = array();
			$pattern = ['@\([^)]+\)@', '@\s+\S+@', '@;@'];
			$replace = ['', '', ''];
			$keyword = preg_replace($pattern, $replace, $data->title);
			exec("/svc/addon/web/flybook/bin/fetchBookScreenBookLoanStatus.php {$company_id} {$data->lib_code} {$keyword}", $out);
			// echo "{$data->title}\n";
			// echo "/svc/addon/web/flybook/bin/fetchBookScreenBookLoanStatus.php {$company_id} {$data->lib_code} {$keyword}";
			$row = @json_decode($out[0], true)['row'];
			// print_r($row);
			$loan_state = isset($row['loanStatus']) ? $row['loanStatus'] : "***";
		} elseif ($company_id == 11){
            // 임상준 2019-06-12 마한도서관
            $data = DB::table('library_book_company11')->select('is_loan')->where('book_id',$book_id)->first();
            if ($data == NULL){
                $loan_state = "***";
                return response()->json(['loan_state'=>$loan_state]);
            }
            $loan_state = "***";
            if ($data->is_loan == '1') $loan_state = "대출가능";
            elseif ($data->is_loan == '2') $loan_state = "대출불가(관내열람가능)";

        } elseif ($company_id == 12){
            // 임상준 2019-07-03 장위행복누림 도서관
            $data = DB::table('library_book_company12')->select('is_loan')->where('book_id',$book_id)->first();
            if ($data == NULL){
                $loan_state = "***";
                return response()->json(['loan_state'=>$loan_state]);
            }
            $loan_state = "대출중";
            if ($data->is_loan == '1') $loan_state = "대출가능";

        } elseif (preg_match("/^(?:13|14|15|16|17)$/", $company_id)){
            // 금천구 도서관 모임
            $lib = '';
            $isbn = $book->isbn;
            switch ($company_id) {
                case 13:
                    $lib = "MA";break;
                case 14:
                    $lib = "BR";break;
                case 15:
                    $lib = "NR";break;
                case 16:
                    $lib = "CB";break;
                case 17:
                    $lib = "DB";break;
            }
            $url = "http://gc.arasoft.kr:48080/frame/search?a_v=&a_qf=I&a_q=".$isbn."&a_bf=M&a_dr=&a_dt=&a_ft=&a_mt=&a_ut=&a_lt=&a_pyFrom=&a_pyTo=&a_sort=A&a_vp=10&a_lib=".$lib;

            // api 요청하기
            $response = Curl::to($url)->get();

            $html = new Htmldom;
    		$html->str_get_html($response);
            $link = trim($html->find('h4',3)->children(0)->href);
            $a_key = explode("a_key=",$link)[1];
            // 책상세페이지 가져오기
            $url_detail = "http://gc.arasoft.kr:48080/frame/search?a_v=&a_qf=I&a_q=".$isbn."&a_bf=M&a_dr=&a_dt=&a_ft=&a_mt=&a_ut=&a_lt=&a_pyFrom=&a_pyTo=&a_sort=A&a_vp=10&a_lib=CB&a_cp=1&a_key=".$a_key;

            $ch = curl_init();
        	curl_setopt($ch, CURLOPT_URL, $url_detail);
        	curl_setopt($ch, CURLOPT_POST, false);
        	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        	$headers = array();
        	curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        	$response_detatil = curl_exec ($ch);
            curl_close ($ch);

            // $response_detatil = str_replace('<script src="../script.js"></script>','<script src="http://gc.arasoft.kr:48080/script.js"></script>',$response_detatil);
            $html2 = new Htmldom;
    		$html2->str_get_html($response_detatil);
            $loan = $html2->find('td[title=BOL112N:]');
// echo $response_detatil;return;
            if( count($loan) > 0 ) $loan_state = "대출가능";
            else $loan_state = "대출중";
		} elseif ($company_id == 21){
			// 동의대 도서관 대출여부 api
			$key = 'sLiMafiacsj293ja10lbi';
			$isbn = $book->isbn;
			$url = 'http://lib.deu.ac.kr/lib/SLIMA.openapi.LendByIsbn.cls?key=' . $key .'&version=1.0&loc=FI&srchKeyword=' . $isbn;
			$response = Curl::to($url)->get();
            $loan_state = $response;
			
            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($response);
            if ($xml === false) {
                // 에러 시 원인 출력
                echo "Failed loading XML: ";
                foreach(libxml_get_errors() as $error) {
                    echo "<br>", $error->message;
                }
            } else {
				$lendstatus = $xml->metadata->item->lendstatus;
				if($lendstatus == 'Y'){
					$loan_state = '대출 가능';
				}else{
					$loan_state = '대출중';
				}
			}
		}
		else {
			$loan_state = "***";
		}
		return response()->json(['loan_state'=>$loan_state]);
	}
}
