<?php

namespace App\Http\Controllers\Library;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Validator;
use DB;
use Curl;
use Auth;
use SoapBox\Formatter\Formatter;

use App\Book;
use App\TimeMaximum;
use App\Http\Controllers\SMSController;
use App\Events\CurlEvent;

class RecombookController extends Controller
{
	public function __construct()
	{
		//DB::connection()->enableQueryLog();
		//$queries = DB::getQueryLog();
		//var_dump($queries);
		//exit;
	}

	public function index(Request $request)
	{ }

	public function show(Request $request, $recombook_id)
	{
		// DB::table('error_log')->insert(['path'=>__FILE__,'line'=>__LINE__,'des'=>"test",'created_at'=>date('Y-m-d H:i:s') ]);
		// @$this->loan_check(16,"9788952239402");return;
		if (isset($request->isChildBook) && $request->isChildBook) {
			$dbh = DB::connection('mysql_noprefix');

			$reqInfo = $dbh->select("
			SELECT
				a.minAge, a.maxAge, b.additionalDescription AS balanceDesc, c.additionalDescription AS balanceDetailDesc
			FROM
				bookscreenChildBookRequestList a
			INNER JOIN
				codeAdditionalInfo b
			ON
				a.balance = b.code
			LEFT JOIN
				codeAdditionalInfo c
			ON
				a.balanceDetail = c.code
			WHERE
				seq = :seq
			", [':seq' => $recombook_id]);

			$list = $dbh->select("
			SELECT
				bookID as book_id, category
			FROM
				bookscreenChildBookResponseList
			WHERE
				requestSeq = :requestSeq
			", ["requestSeq" => $recombook_id]);

			foreach ($list as $item) {
				$item->book = \App\Book::select('id', 'title', 'book_img', 'author', 'publisher', 'page', 'pubdate')->where('id', $item->book_id)->first();
				switch ($item->category) {
					case 'SM01':
						$item->title = "지금 {$reqInfo[0]->minAge}~{$reqInfo[0]->maxAge}세가<br/>읽으면 좋은 책이에요";
						break;
					case 'SM02':
						$item->title = "{$reqInfo[0]->balanceDesc}에 관심이 많은 사람들은<br/>이 책도 읽었어요!";
						break;
					case 'SM03':
						$item->title = "{$reqInfo[0]->balanceDetailDesc}에 관심이 많은 당신에게<br/>이 책을 추천해요!";
						break;
				}
			}
		} else {
			$list = \App\LibraryRecombook::where('survey_id', $recombook_id)->get();
			foreach ($list as $item) {
				$item->book = \App\Book::select('id', 'title', 'book_img', 'author', 'publisher', 'page', 'pubdate')->where('id', $item->book_id)->first();
			}
		}


		return response()->json($list);
	}

	public function getChildRecombook(Request $request, $requestSeq)
	{
		$dbh = DB::connection('mysql_noprefix');

		$reqInfo = $dbh->select("
		SELECT
			a.minAge, a.maxAge, a.balance, a.balanceDetail, b.additionalDescription AS balanceDesc, c.additionalDescription AS balanceDetailDesc
		FROM
			bookscreenChildBookRequestList a
		INNER JOIN
			codeAdditionalInfo b
		ON
			a.balance = b.code
		LEFT JOIN
			codeAdditionalInfo c
		ON
			a.balanceDetail = c.code
		WHERE
			seq = :seq
		", [':seq' => $requestSeq]);


		$row = $dbh->select("
		SELECT
			a.bookID, a.category, b.title, b.book_img, b.author, b.publisher, b.page, b.pubdate, CONCAT(c.minAge, '세~',c.maxAge, '세') as ageRange, c.keywordList
		FROM
			bookscreenChildBookResponseList a
		INNER JOIN
			fly_book b
		ON
			a.bookID = b.id
		INNER JOIN
			ibookcareBookInfo c
		ON
			a.bookID = c.bookID
		WHERE
			a.requestSeq = :requestSeq
		", [':requestSeq' => $requestSeq]);

		foreach ($row as $k => $v) {
			switch ($v->category) {
				case 'SM01':
					$v->comment = "지금 {$reqInfo[0]->minAge}~{$reqInfo[0]->maxAge}세가<br/>읽으면 좋은 책이에요";
					break;
				case 'SM02':
					$v->comment = "{$reqInfo[0]->balanceDesc}에 관심이 많은 사람들은<br/>이 책도 읽었어요!";
					break;
				case 'SM03':
					$v->comment = "{$reqInfo[0]->balanceDetailDesc}에 관심이 많은 당신에게<br/>이 책을 추천해요!";
					break;
			}

			$keywordList = explode(",", $v->keywordList);
			$tags = "";
			foreach ($keywordList as $subk => $subv) {
				$tags .= (!empty($tags) ? " " : "") . "$#{$subv}";
			}

			$row[0]->tags = $tags;
		}

		return response()->json($row);
	}

	public function sendPhone(Request $request, $recombook_id)
	{
		$dbh = DB::connection('mysql_noprefix');
		$company_id = $request->input('company_id', 1);
		$messages = [
			'phone_number.required' => '휴대폰번호가 필요합니다.',
			'phone_number.between' => '휴대폰번호 자리수가 맞지 않습니다.',

		];

		$validator = Validator::make($request->all(), [
			'phone_number' => 'required|between:10,13',
		], $messages);

		if ($validator->fails()) {
			return response()->json(['error' => $validator->errors()->first()], 400);
		}

		$sms = new SMSController;
		$phone_number = trim(str_replace("-", "", $request->phone_number));

		$company = DB::select("
		SELECT
			company_name as name
		FROM
			fly_library_company
		WHERE
			id = :company_id
		", ['company_id' => $company_id])[0];

		$is_child = 1;
		if (isset($request->isChildBook) && $request->isChildBook) {
			$is_child = 2;
			$reqInfo = $dbh->select("
			SELECT
				a.minAge, a.maxAge, b.additionalDescription AS balanceDesc, c.additionalDescription AS balanceDetailDesc
			FROM
				bookscreenChildBookRequestList a
			INNER JOIN
				codeAdditionalInfo b
			ON
				a.balance = b.code
			LEFT JOIN
				codeAdditionalInfo c
			ON
				a.balanceDetail = c.code
			WHERE
				seq = :seq
			", [':seq' => $recombook_id]);

			$list = $dbh->select("
			SELECT
				bookID as book_id, category
			FROM
				bookscreenChildBookResponseList
			WHERE
				requestSeq = :requestSeq
			", ["requestSeq" => $recombook_id]);
		} else {
			$survey = \App\LibrarySurvey::where('id', $recombook_id)->first();
			$list = \App\LibraryRecombook::where('survey_id', $recombook_id)->get();
		}

		foreach ($list as $item) {
			$item->book = \App\Book::select('id', 'title')->where('id', $item->book_id)->first();

			if (isset($request->isChildBook) && $request->isChildBook) {
				switch ($item->category) {
					case 'SM01':
						$item->title = "지금 {$reqInfo[0]->minAge}~{$reqInfo[0]->maxAge}세가<br/>읽으면 좋은 책이에요";
						break;
					case 'SM02':
						$item->title = "{$reqInfo[0]->balanceDesc}에 관심이 많은 사람들은<br/>이 책도 읽었어요!";
						break;
					case 'SM03':
						$item->title = "{$reqInfo[0]->balanceDetailDesc}에 관심이 많은 당신에게<br/>이 책을 추천해요!";
						break;
				}
			}

			$item->title = str_replace(array("<br>", "<br/>"), " ", $item->title);
			if (strpos($item->book->title, '(') !== false) {
				$item->book->title = trim(substr($item->book->title, 0, strpos($item->book->title, '(')));
			}
			$tableName = ($company_id <= 2) ? "fly_library_book" : "fly_library_book_company{$company_id}";
			$item->lib_book = DB::select("
			SELECT
				IFNULL(b.search_code, '-') AS search_code, b.location_config_id
			FROM
				fly_book a
			LEFT JOIN
				{$tableName} b
			ON
				a.id = b.book_id
			WHERE
				a.id = :book_id
			", ['book_id' => $item->book_id])[0];

			if (!empty($item->lib_book->location_config_id)) {
				$item->lib_book->location_config = DB::select("
				SELECT
					text
				FROM
					fly_library_location_config
				WHERE
					id = :id
				", ['id' => $item->lib_book->location_config_id])[0];
			} else {
				$item->lib_book->location_config = (object) ['text' => '-'];
			}
		}
		// 도서관별 추적가능한 플라이북 앱 연결 링크
		$custom_link = "goo.gl/gtTRFU";
		$coupon_code = "아직 열린 쿠폰 이벤트가 없어요.";
		if ($company_id == 1) {
			// 서초구립반포도서관
			$custom_link = "https://flybook.page.link/01";
			$coupon_code = "S195C3";
		} else if ($company_id == 2) {
			// 플라이북스크린
			$custom_link = "https://flybook.page.link/ZD";
			$coupon_code = "지속가능한도서관";
		} else if ($company_id == 3) {
			// 경상북도교육청 외동도서관
			$custom_link = "https://flybook.page.link/02";
			$coupon_code = "E957M8";
		} else if ($company_id == 4) {
			// 용인시 청덕도서관
			$custom_link = "https://flybook.page.link/03";
			$coupon_code = "C643W9";
		} else if ($company_id == 5) {
			// 서울창업허브도서관
			$custom_link = "https://flybook.page.link/05";
			$coupon_code = "S953H3";
		} else if ($company_id == 6) {
			// 용인어린이상상의숲
			$custom_link = "https://flybook.page.link/04";
			$coupon_code = "용인어린이상상의숲";
		} else if ($company_id == 7) {
			// 광주장덕도서관
			$custom_link = "https://flybook.page.link/06";
			$coupon_code = "G486J4";
		} else if ($company_id == 8) {
			// 광주신가도서관
			$custom_link = "https://flybook.page.link/07";
			$coupon_code = "G654S8";
		} else if ($company_id == 9) {
			// 양평중앙도서관 
			$custom_link = "https://flybook.page.link/08";
			$coupon_code = "Y783J9";
		} else if ($company_id == 10) {
			// 양평용문도서관
			$custom_link = "https://flybook.page.link/09";
			$coupon_code = "Y924Q6";
		} else if ($company_id == 11) {
			// 마한교육문화도서관
			$custom_link = "https://flybook.page.link/10";
			$coupon_code = "M729G7";
		} else if ($company_id == 12) {
			// 장위행복누림도서관
			$custom_link = "https://flybook.page.link/11";
			$coupon_code = "J583H5";
		} else if ($company_id == 13) {
			// 독산도서관
			$custom_link = "https://flybook.page.link/13";
			$coupon_code = "D622Y5";
		} else if ($company_id == 14) {
			// 가산도서관
			$custom_link = "https://flybook.page.link/12";
			$coupon_code = "G473K7";
		} else if ($company_id == 15) {
			// 금나래도서관
			$custom_link = "https://flybook.page.link/14";
			$coupon_code = "G367L1";
		} else if ($company_id == 16) {
			// 시흥도서관
			$custom_link = "https://flybook.page.link/15";
			$coupon_code = "S479P5";
		} else if ($company_id == 17) {
			// 미래향기작은도서관
			$custom_link = "https://flybook.page.link/16";
			$coupon_code = "M26498";
		} else if ($company_id == 18) {
			// 하동도서관
			$custom_link = "https://flybook.page.link/18";
			$coupon_code = "H46375";
		} else if ($company_id == 19) {
			// 성북 연합 (장위행복누림도서관과 연동하기 때문에 임시로 만들어 놓은 이벤트성)
			$custom_link = "https://flybook.page.link/11";
			$coupon_code = "J583H5";
		} else if ($company_id == 20) {
			// 양천 도서관
			$custom_link = "https://flybook.page.link/20";
			$coupon_code = "Y587H5";
		} else if ($company_id == 21) {
			// 역삼 도서관
			$custom_link = "https://flybook.page.link/21";
			$coupon_code = "YS28NE";
		} else if ($company_id == 22) {
			// 답십리 도서관
			$custom_link = "https://flybook.page.link/22";
			$coupon_code = "DSN22L";
		} else if ($company_id == 23) {
			// 청담 도서관
			$custom_link = "https://flybook.page.link/23";
			$coupon_code = "CD113L";
		} else if ($company_id == 24) {
			// 동의대 도서관
			$custom_link = "https://flybook.page.link/24";
			$coupon_code = "D587U5";
		} else if ($company_id == 25) {
			// 목포 시립 도서관
			$custom_link = "https://flybook.page.link/25";
			$coupon_code = "M9C4L1";
		} else if ($company_id == 26) {
			// 대구 북구 구수산 도서관
			$custom_link = "https://flybook.page.link/26";
			$coupon_code = "D9G4L3";
		} else if ($company_id == 27) {
			// 대구 북구 태전 도서관
			$custom_link = "https://flybook.page.link/27";
			$coupon_code = "D8T3L2";
		} else if ($company_id == 28) {
			// 대구 북구 대현 도서관
			$custom_link = "https://flybook.page.link/28";
			$coupon_code = "D7D3L2";
		} else if ($company_id == 29) {
			// 남양주 별빛 도서관
			$custom_link = "https://flybook.page.link/29";
			$coupon_code = "N7S3L1";
		}

		$message = '[' . $company->name . '_플라이북 스크린]

지금 추천 받으신 책정보가 도착했습니다.

';

		$book_message = '';
		foreach ($list as $item) {
			$book_message .= "\"{$item->title}\"\n";
			$book_message .= '<' . $item->book->title . '>
▷자료실/서가
: ' . $item->lib_book->location_config->text . '
: ' . $item->lib_book->search_code . '
▷상세정보
: http://www.flybook.kr/book/' . $item->book->id . '

';
		}

		$message .= $book_message;
		$message .= "
언제 어디서나 플라이북 앱에서도 책을 추천받을 수 있어요.

도서관 이용자분들을 위해
책 구매 시 사용 가능한
독서지원금 3,000p를 드려요!
여러분의 독서를 응원합니다 ♡

▷독서지원금 쿠폰
쿠폰코드 : " . $coupon_code .
			"
유효기간 : 오늘(" . date('m') . "월/" . date('d') . "일) 24시까지

▷쿠폰 사용 방법
1) 플라이북 앱 실행
2) 내 정보에서 포인트 선택
3) 쿠폰코드 등록

▷사용하러 가기 
"
			. $custom_link;
		/*$coupon = "용문도서관";
        if ($company_id == 1) $coupon = "서초도서관";
        elseif ($company_id == 2) $coupon = "서초도서관";
        elseif ($company_id == 3) $coupon = "외동도서관";
        elseif ($company_id == 4) $coupon = "청덕도서관";
        elseif ($company_id == 5) $coupon = "서울창업허브도서관";
        elseif ($company_id == 6) $coupon = "용인국제어린이도서관";
        elseif ($company_id == 7) $coupon = "장덕도서관";
        elseif ($company_id == 8) $coupon = "신가도서관";
        elseif ($company_id == 9) $coupon = "양평중앙도서관";

        $message .= "

세계 책의 날 기념 독서지원금 증정 쿠폰(~4/30)
*쿠폰코드: ".$coupon."
*이용
*이용방법: 아래 링크 클릭 후 쿠폰코드를 입력하세요.
*링크클릭!> goo.gl/gtTRFU
";*/

		$response = $sms->postKakao($message, $phone_number, 1);
		// 임상준 2019-06-17 sms 로그 남기기
		$arr = [];
		$arr['company_id'] = $company_id;
		$arr['survey_id'] = $recombook_id;
		$arr['phone'] = $phone_number;
		$arr['is_child'] = $is_child;
		$arr['kakao_response'] = $response['result_code'] . "_" . $response['result'] . "_" . $response['template_code'] . "_" . $response['id'];
		$arr['created_at'] = date('Y-m-d H:i:s');
		$arr['updated_at'] = date('Y-m-d H:i:s');
		DB::table('library_sms_log')->insert($arr);

		return response()->json($response);
	}

	public function showSurvey(Request $request, $recombook_id)
	{
		$survey = \App\LibrarySurvey::where('id', $recombook_id)->first();

		return response()->json($survey);
	}

	public function store(Request $request)
	{
		$dbh = DB::connection('mysql_noprefix');
		// $request->company_id = 11;
		if (!isset($request->balance)) {
			$survey = new \App\LibrarySurvey;
			$survey->company_id = $request->company_id;
			$survey->sex = $request->sex;
			$survey->age = $request->age;
			$survey->marriage = $request->marriage;
			$survey->feeling = $request->feeling;
			$survey->interest = $request->interest;
			$survey->book_genre = $request->book_genre;
			$survey->save();

			$this->makeRecombook($survey, $request->company_id, $request->enableSuggestBookOtherThanCollectionBook);
			return response()->json($survey);
		} else {
			$bookIdList = [];
			$response = [];
			$match = [];
			preg_match("/(\d+)-(\d+)/", $request->ageRange, $match);
			$minAge = $match[1];
			$maxAge = $match[2];
			// return response()->json($match);
			$dbh->beginTransaction();
			$bookList = [];
			try {
				$bind = [
					':companyID' => $request->company_id,
					':minAge' => $minAge,
					':maxAge' => $maxAge,
					':sex' => ($request->sex == 'M') ? 'SF01' : 'SF02',
					':balance' => $request->balance,
					':balanceDetail' => $request->balanceDetail
				];
				$dbh->insert("
				INSERT INTO bookscreenChildBookRequestList (companyID, minAge, maxAge, sex, balance, balanceDetail, regDate)
				VALUES (:companyID, :minAge, :maxAge, :sex, :balance, :balanceDetail, NOW())
				", $bind);
				$table = ($request->company_id <= 2) ? "fly_library_book z" : "fly_library_book_company{$request->company_id} z";

				$row = $dbh->select("
				SELECT LAST_INSERT_ID() AS requestSeq
				");

				$requestSeq = $row[0]->requestSeq;
				$response['id'] = $requestSeq;
				// return response()->json($response);
				// $response['id'] = $request['dbh']->lastInsertId();

				// 나이대 추천 1권
				$bind = [
					':minAge' => $minAge,
					':maxAge' => $maxAge
				];
				// $joinType = ($request->enableSuggestBookOtherThanCollectionBook && rand(1,3) == 1) ? "LEFT" : "INNER";
				// $joinType = ($request->enableSuggestBookOtherThanCollectionBook) ? "LEFT" : "INNER";
				$joinType = "LEFT";
				$row = array();
				$row = $dbh->select("
				SELECT
					a.bookID,
                    z.isbn
				FROM
					ibookcareBookInfo a
				{$joinType} JOIN
					{$table}
				ON
					a.bookID = z.book_id
				WHERE
					a.minAge <= :minAge AND a.maxAge >= :maxAge
				ORDER BY
					rand()
				LIMIT 1
				", $bind);

				// print_r($row[0]->bookID);
				// exit;
				foreach ($row as $k => $v) {
					if (isset($v->bookID)) {
						array_push($bookIdList, $v->bookID);
						array_push($bookList, ['bookID' => $v->bookID, 'category' => 'SM01']);
						@$this->loan_check($request->company_id, $v->isbn);
					}
				}


				// 관심사에서 추천 1 ~ 2권
				$fetchCount = isset($request->balanceDetail) ? 1 : 2;
				$bind = [
					':minAge' => $minAge,
					':maxAge' => $maxAge,
					':balance' => $request->balance
				];
				// $joinType = ($request->enableSuggestBookOtherThanCollectionBook && rand(1,3) == 1) ? "LEFT" : "INNER";
				// ()

				$clause['condition'] = "";
				if (!empty($bookIdList)) {
					$clause['condition'] = " AND a.bookID NOT IN " . "(" . implode(",", $bookIdList) . ")";
				}
				// $joinType = ($request->enableSuggestBookOtherThanCollectionBook) ? "LEFT" : "INNER";
				$joinType = "LEFT";
				$row = array();
				$row = $dbh->select("
				SELECT
					a.bookID,
                    z.isbn
				FROM
					ibookcareBookInfo a
				INNER JOIN
					ibookcareBalance b
				ON
					a.bookID = b.bookID
				{$joinType} JOIN
					{$table}
				ON
					a.bookID = z.book_id
				WHERE
					(a.minAge <= :minAge AND a.maxAge >= :maxAge) AND (b.item = :balance AND b.score > 0.01){$clause['condition']}
				ORDER BY
					rand()
				LIMIT $fetchCount
				", $bind);

				foreach ($row as $k => $v) {
					if (isset($v->bookID)) {
						array_push($bookIdList, $v->bookID);
						array_push($bookList, ['bookID' => $v->bookID, 'category' => 'SM02']);
						@$this->loan_check($request->company_id, $v->isbn);
					}
				}

				// 관심사상세에서 추천 1 ~ 2권
				if (isset($request->balanceDetail)) {
					$bind = [
						':minAge' => $minAge,
						':maxAge' => $maxAge,
						':balance' => $request->balance,
						':balanceDetail' => $request->balanceDetail
					];
					$clause['condition'] = "";
					if (!empty($bookIdList)) {
						$clause['condition'] = " AND a.bookID NOT IN " . "(" . implode(",", $bookIdList) . ")";
					}
					// $joinType = ($request->enableSuggestBookOtherThanCollectionBook && rand(1,3) == 1) ? "LEFT" : "INNER";
					// $joinType = ($request->enableSuggestBookOtherThanCollectionBook) ? "LEFT" : "INNER";
					$joinType = "LEFT";
					$row = array();
					$row = $dbh->select("
					SELECT
						a.bookID,
                        z.isbn
					FROM
						ibookcareBookInfo a
					INNER JOIN
						ibookcareBalance b
					ON
						a.bookID = b.bookID
					INNER JOIN
						ibookcareBalanceDetail c
					ON
						a.bookID = c.bookID
					{$joinType} JOIN
						{$table}
					ON
						a.bookID = z.book_id
					WHERE
						(a.minAge >= :minAge AND a.maxAge >= :maxAge) AND (b.item = :balance AND b.score > 0.01) AND (c.item = :balanceDetail AND c.score > 10){$clause['condition']}
					ORDER BY
						rand()
					LIMIT 1
					", $bind);

					foreach ($row as $k => $v) {
						if (isset($v->bookID)) {
							array_push($bookList, ['bookID' => $v->bookID, 'category' => 'SM03']);
							@$this->loan_check($request->company_id, $v->isbn);
						}
					}
				}

				// return response()->json($bookList);
				foreach ($bookList as $k => $v) {
					// print_r($v);
					$bind = [
						':requestSeq' => $requestSeq,
						':bookID' => $v['bookID'],
						':category' => $v['category']
					];
					$dbh->insert("
					INSERT INTO bookscreenChildBookResponseList (requestSeq, bookID, category)
					VALUES (:requestSeq, :bookID, :category)
					", $bind);
				}
			} catch (Exception $e) {
				$dbh->rollBack();
			}

			$dbh->commit();
			// $dbh->rollBack();

			return response()->json($response);
		}
	}

	public function makeRecombook($survey, $company_id = 1, $enableSuggestBookOtherThanCollectionBook = false)
	{

		$sex = $survey->sex;
		$age = $survey->age;
		$marriage = $survey->marriage;
		$feeling_id = $survey->feeling;
		$interest_id = $survey->interest;
		$book_genre = $survey->book_genre;

		// $bingo = ['feeling'];
		$bingo = ['feeling', 'interest', 'age_sex'];

		shuffle($bingo);

		$read_book_id_arr = [];

		for ($i = 0; $i < sizeof($bingo); $i++) {
			if ($bingo[$i] == 'feeling') {
				$feeling_book = $this->getFeelingBook($age, $sex, $marriage, $book_genre, $feeling_id, $read_book_id_arr, $company_id, $enableSuggestBookOtherThanCollectionBook);

				if ($feeling_book) {
					unset($input_data);
					$input_data['feeling_num'] = $feeling_book['feeling']->id;
					$input_data['feeling_name'] = $feeling_book['feeling']->feeling_name;
					$input_data['sex'] = $sex;
					$input_data['age'] = $age;
					$input_data['is_love'] = $marriage;

					$libraryRecombook = new \App\LibraryRecombook;
					$libraryRecombook->title = $this->getFeelingSubject($input_data);
					$libraryRecombook->survey_id = $survey->id;
					$libraryRecombook->book_id = $feeling_book['book']->id;
					$libraryRecombook->library_book_id = $feeling_book['book']->id;
					$libraryRecombook->save();

					array_push($read_book_id_arr, $feeling_book['book']->id);
					@$this->loan_check($company_id, $feeling_book['book']->isbn);
				}
			}
			if ($bingo[$i] == 'interest') {
				$interest_book = $this->getInterestBook($age, $sex, $marriage, $book_genre, $interest_id, $read_book_id_arr, $company_id, $enableSuggestBookOtherThanCollectionBook);

				if ($interest_book) {
					unset($input_data);
					$input_data['interest_num'] = $interest_book['interest']->id;
					$input_data['interest_name'] = $interest_book['interest']->interest_name;
					$input_data['sex'] = $sex;
					$input_data['age'] = $age;
					$input_data['is_love'] = $marriage;

					$libraryRecombook = new \App\LibraryRecombook;
					$libraryRecombook->title = $this->getInterestSubject($input_data);
					$libraryRecombook->survey_id = $survey->id;
					$libraryRecombook->book_id = $interest_book['book']->id;
					$libraryRecombook->library_book_id = $interest_book['book']->id;
					$libraryRecombook->save();

					array_push($read_book_id_arr, $interest_book['book']->id);
					@$this->loan_check($company_id, $interest_book['book']->isbn);
				}
			}
			if ($bingo[$i] == 'age_sex') {
				$best_age_sex_book = $this->getBestAgeSexBook($sex, $age, $marriage, $book_genre, $read_book_id_arr, $company_id, $enableSuggestBookOtherThanCollectionBook);

				if ($best_age_sex_book) {
					$libraryRecombook = new \App\LibraryRecombook;
					$libraryRecombook->title = $age . "대 " . $this->getSexText($sex) . "가<br>읽으면 좋은 책이에요!";
					$libraryRecombook->survey_id = $survey->id;
					$libraryRecombook->book_id = $best_age_sex_book->id;
					$libraryRecombook->library_book_id = $best_age_sex_book->id;
					$libraryRecombook->save();

					array_push($read_book_id_arr, $best_age_sex_book->id);
					@$this->loan_check($company_id, $best_age_sex_book->isbn);
				}
			}
		}
	}

	// 임상준 2019-06-13 대출여부 업데이트 시키기(현재 마한도서관만 사용)
	public function loan_check($c_id, $isbn)
	{
		if (empty($isbn)) return;

		$arr = [];
		$title = null;

		if ($c_id == 11) {
			// 마한도서관 도서관시간 아닐시 대출여부 확인안함
			$hours = date('H');     // 시간확인
			if ($hours > 20 || $hours < 10) return;

			$dayOfWeek = date("w");     // 요일확인
			// if ($dayOfWeek == 0 || $dayOfWeek == 6) return;
		}

		// 대출여부 갱신처리 하기
		$arr['company_id'] = $c_id;
		$arr['isbn'] = $isbn;

		event(new CurlEvent($arr));

		return;
	}

	public function makeRecombook2()
	{

		$survey = \App\LibrarySurvey::where('id', 676)->first();

		$sex = $survey->sex;
		$age = $survey->age;
		$marriage = $survey->marriage;
		$feeling_id = $survey->feeling;
		$interest_id = $survey->interest;
		$book_genre = $survey->book_genre;

		$bingo = ['feeling', 'interest', 'age_sex'];

		shuffle($bingo);

		$read_book_id_arr = [];

		for ($i = 0; $i < 3; $i++) {
			if ($bingo[$i] == 'feeling') {
				$feeling_book = $this->getFeelingBook($age, $sex, $marriage, $book_genre, $feeling_id, $read_book_id_arr);

				if ($feeling_book) {
					unset($input_data);
					$input_data['feeling_num'] = $feeling_book['feeling']->id;
					$input_data['feeling_name'] = $feeling_book['feeling']->feeling_name;
					$input_data['sex'] = $sex;
					$input_data['age'] = $age;
					$input_data['is_love'] = $marriage;

					$libraryRecombook = new \App\LibraryRecombook;
					$libraryRecombook->title = $this->getFeelingSubject($input_data);
					$libraryRecombook->survey_id = $survey->id;
					$libraryRecombook->book_id = $feeling_book['book']->id;
					$libraryRecombook->library_book_id = $feeling_book['book']->id;
					$libraryRecombook->save();

					array_push($read_book_id_arr, $feeling_book['book']->id);
				}
			}
			if ($bingo[$i] == 'interest') {
				$interest_book = $this->getInterestBook($age, $sex, $marriage, $book_genre, $interest_id, $read_book_id_arr);

				if ($interest_book) {
					unset($input_data);
					$input_data['interest_num'] = $interest_book['interest']->id;
					$input_data['interest_name'] = $interest_book['interest']->interest_name;
					$input_data['sex'] = $sex;
					$input_data['age'] = $age;
					$input_data['is_love'] = $marriage;

					$libraryRecombook = new \App\LibraryRecombook;
					$libraryRecombook->title = $this->getInterestSubject($input_data);
					$libraryRecombook->survey_id = $survey->id;
					$libraryRecombook->book_id = $interest_book['book']->id;
					$libraryRecombook->library_book_id = $interest_book['book']->id;
					$libraryRecombook->save();

					array_push($read_book_id_arr, $interest_book['book']->id);
				}
			}
			if ($bingo[$i] == 'age_sex') {
				$best_age_sex_book = $this->getBestAgeSexBook($sex, $age, $marriage, $book_genre, $read_book_id_arr);

				if ($best_age_sex_book) {
					$libraryRecombook = new \App\LibraryRecombook;
					$libraryRecombook->title = $age . "대 " . $this->getSexText($sex) . "가<br>읽으면 좋은 책이에요!";
					$libraryRecombook->survey_id = $survey->id;
					$libraryRecombook->book_id = $best_age_sex_book->id;
					$libraryRecombook->library_book_id = $best_age_sex_book->id;
					$libraryRecombook->save();

					array_push($read_book_id_arr, $best_age_sex_book->id);
				}
			}
		}

		$list = \App\LibraryRecombook::where('survey_id', $survey->id)->get();
		return response()->json($list);
	}

	public function getFeelingBook($age, $sex, $marriage, $book_genre, $feeling_id, $read_book_id_arr, $company_id = 1, $enableSuggestBookOtherThanCollectionBook = false)
	{
		$user_sex = $this->getSexKorString($sex);
		$user_married = $this->getMarriedKorString($marriage);

		$join_table = ($company_id <= 2) ? "library_book" : "library_book_company{$company_id}";
		// if ($enableSuggestBookOtherThanCollectionBook) {
		if ($enableSuggestBookOtherThanCollectionBook && rand(1, 3) == 1) {
			$book_arr1 = DB::table('book_feeling')
				->join("book", "book_feeling.book_id", '=', "book.id")
				->leftJoin("{$join_table}", 'book_feeling.book_id', '=', "{$join_table}.book_id")
				->where('config_book_feeling_id', $feeling_id)
				->whereNull("{$join_table}.id")
				->whereRaw("fly_book.pubdate > '" . date('Y-m-d', strtotime('-3 years')) . "'")
				->pluck("book_feeling.book_id");
			if (count($book_arr1) < 1) {
				$book_arr1 = DB::table('book_feeling')
					->join("{$join_table}", 'book_feeling.book_id', '=', "{$join_table}.book_id")
					->where('config_book_feeling_id', $feeling_id)
					->pluck("{$join_table}.book_id");
			}
		} else {
			$book_arr1 = DB::table('book_feeling')
				->join("{$join_table}", 'book_feeling.book_id', '=', "{$join_table}.book_id")
				->where('config_book_feeling_id', $feeling_id)
				->pluck("{$join_table}.book_id");
		}


		$query = \App\Book::select('id', 'title', 'publisher', 'author', 'book_img', 'isbn')
			->wherein('sex', ['모두', $user_sex])
			->wherein('married', ['모두', $user_married])
			->wherein('id', $book_arr1)
			->whereNotIn('id', $read_book_id_arr)
			->orderBy(DB::raw('rand()'));

		$query->whereHas('ages', function ($query) use ($age) {
			$query->where('config_book_age_id', $age / 10);
		});


		if ($book_genre == 'literature' || $book_genre == 'practical') {
			$query->whereIn('genre', [$book_genre, 'none']);
		}

		$book = $query->first();

		if ($book) {
			$feeling = \App\ConfigBookFeeling::find($feeling_id);
		}

		if (empty($book)) {
			return null;
		}

		return ['book' => $book, 'feeling' => $feeling];
	}

	public function getInterestBook($age, $sex, $marriage, $book_genre, $interest_id, $read_book_id_arr, $company_id = 1, $enableSuggestBookOtherThanCollectionBook = false)
	{
		$user_sex = $this->getSexKorString($sex);
		$user_married = $this->getMarriedKorString($marriage);

		$join_table = ($company_id <= 2) ? "library_book" : "library_book_company{$company_id}";
		// if ($enableSuggestBookOtherThanCollectionBook) {
		if ($enableSuggestBookOtherThanCollectionBook && rand(1, 3) == 1) {
			$book_arr1 = DB::table('book_interest')
				->join("book", "book_interest.book_id", '=', "book.id")
				->leftJoin("{$join_table}", 'book_interest.book_id', '=', "{$join_table}.book_id")
				->where('config_book_interest_id', $interest_id)
				->whereNull("{$join_table}.id")
				->whereRaw("fly_book.pubdate > '" . date('Y-m-d', strtotime('-3 years')) . "'")
				->pluck("book_interest.book_id");
			if (count($book_arr1) < 1) {
				$book_arr1 = DB::table('book_interest')
					->join("{$join_table}", 'book_interest.book_id', '=', "{$join_table}.book_id")
					->where('config_book_interest_id', $interest_id)
					->pluck("{$join_table}.book_id");
			}
		} else {
			$book_arr1 = DB::table('book_interest')
				->join("{$join_table}", 'book_interest.book_id', '=', "{$join_table}.book_id")
				->where('config_book_interest_id', $interest_id)
				->pluck("{$join_table}.book_id");
		}

		$query = \App\Book::select('id', 'title', 'publisher', 'author', 'book_img', 'isbn')
			->wherein('sex', ['모두', $user_sex])
			->wherein('married', ['모두', $user_married])
			->wherein('id', $book_arr1)
			->whereNotIn('id', $read_book_id_arr)
			->orderBy(DB::raw('rand()'));

		$query->whereHas('ages', function ($query) use ($age) {
			$query->where('config_book_age_id', $age / 10);
		});

		if ($book_genre == 'literature' || $book_genre == 'practical') {
			$query->whereIn('genre', [$book_genre, 'none']);
		}

		$book = $query->first();

		if ($book) {
			$interest = \App\ConfigBookInterest::find($interest_id);
		}

		if (empty($book)) {
			return null;
		}

		return ['book' => $book, 'interest' => $interest];
	}

	public function getBestAgeSexBook($sex, $age, $marriage, $book_genre, $read_book_id_arr, $company_id = 1, $enableSuggestBookOtherThanCollectionBook = false)
	{
		$user_sex = $this->getSexKorString($sex);
		$config_book_age_id = (int) $age / 10;
		$user_married = $this->getMarriedKorString($marriage);

		$join_table = ($company_id <= 2) ? "library_book" : "library_book_company{$company_id}";
		// if ($enableSuggestBookOtherThanCollectionBook) {
		if ($enableSuggestBookOtherThanCollectionBook && rand(1, 3) == 1) {
			$book_arr1 = DB::table('book_age')
				->join("book", "book_age.book_id", '=', "book.id")
				->leftJoin("{$join_table}", 'book_age.book_id', '=', "{$join_table}.book_id")
				->where('config_book_age_id', $config_book_age_id)
				->whereNull("{$join_table}.id")
				->whereRaw("fly_book.pubdate > '" . date('Y-m-d', strtotime('-3 years')) . "'")
				->pluck("book_age.book_id");
			if (count($book_arr1) < 1) {
				$book_arr1 = DB::table('book_age')
					->join("{$join_table}", 'book_age.book_id', '=', "{$join_table}.book_id")
					->where('config_book_age_id', $config_book_age_id)
					->pluck("{$join_table}.book_id");
			}
		} else {
			$book_arr1 = DB::table('book_age')
				->join("{$join_table}", 'book_age.book_id', '=', "{$join_table}.book_id")
				->where('config_book_age_id', $config_book_age_id)
				->pluck("{$join_table}.book_id");
		}

		$query = \App\Book::select('id', 'title', 'publisher', 'author', 'book_img', 'isbn')
			->wherein('sex', ['모두', $user_sex])
			->wherein('married', ['모두', $user_married])
			->wherein('id', $book_arr1)
			->whereNotIn('id', $read_book_id_arr)
			->orderBy(DB::raw('rand()'));

		if ($book_genre == 'literature' || $book_genre == 'practical') {
			$query->whereIn('genre', [$book_genre, 'none']);
		}

		$book = $query->first();

		if (empty($book)) {
			return null;
		}

		return $book;
	}

	public function getConfigId($list, $search_value)
	{
		foreach ($list as $item) {
			if ($item->text == $search_value) return $item->id;
		}
	}

	public function getFeelingSubject($data)
	{
		$subject = '';

		#슬플 때
		if ($data['feeling_num'] == 3) :
			$subject = '갑작스런 슬픔에 위로가 필요한<br>';

		#용기가 필요할 때
		elseif ($data['feeling_num'] == 20) :
			$subject = '따뜻한 위로와 격려가 필요한<br>';

		#이별했을 때
		elseif ($data['feeling_num'] == 6) :
			$subject = '사랑하는 사람과 이별한<br>';

		#행복할 때
		elseif ($data['feeling_num'] == 7) :
			$subject = '내일도 오늘처럼 행복하고 싶은<br>';

		#사랑할 때
		elseif ($data['feeling_num'] == 8) :
			$subject = '지금 누군가를 사랑하고 있는<br>';

		#무기력할 때
		elseif ($data['feeling_num'] == 15) :
			$subject = '일상 속 새로운 즐거움이 필요한<br>';

		#외로울 때
		elseif ($data['feeling_num'] == 9) :
			$subject = '오늘따라 유난히 쓸쓸한<br>';
		#심심할 때
		elseif ($data['feeling_num'] == 21) :
			$subject = '오랜만에 여유가 생긴<br>';

		#불안할 때
		elseif ($data['feeling_num'] == 5) :
			$subject = '어딘지 모르게 마음이 불안한<br>';

		#고민될 때
		elseif ($data['feeling_num'] == 22) :
			$subject = '요즘 부쩍 머릿속이 복잡한<br>';

		#답답할 때
		elseif ($data['feeling_num'] == 13) :
			$subject = '마음이 답답하고 어지러운<br>';

		#힐링이 필요할 때
		elseif ($data['feeling_num'] == 23) :
			$subject = '바쁜 일상 속에서 힐링이 필요한<br>';

		#힘들 때
		elseif ($data['feeling_num'] == 14) :
			$subject = '요즘 하루하루가 지치고 힘든<br>';

		#떠나고 싶을 때
		elseif ($data['feeling_num'] == 10) :
			$subject = '지친 일상 속에서 일탈을 꿈꾸는<br>';

		endif;

		if ($subject == '') {
			$subject = $data['feeling_name'] . '에 읽으면 좋은 책';
		} else {
			/*
			if($data['age'] && $data['job']) :
				$subject .= $data['age'].'대 '.$data['job']->job_name.'에게 이 책을 추천해요!';
			elseif($data['age']) :
				$subject .= $data['age'].'대에게 이 책을 추천해요!';
			elseif($data['job']) :
				$subject .= $data['job'].'에게 이 책을 추천해요!';
			else :
				$subject .= '당신에게 이 책을 추천해요!';
			endif;
			*/
			$subject .= '당신에게 추천해요!';
		}

		return $subject;
	}

	public function getInterestSubject($data)
	{
		$subject = '';

		#여행
		if ($data['interest_num'] == 7) :
			$subject = '어디론가 떠나고 싶은<br>';

		#진로
		elseif ($data['interest_num'] == 9) :
			$subject = '이루고 싶은 것이 많은<br>';

		#기획/마케팅
		elseif ($data['interest_num'] == 46) :
			$subject = '늘 새로운 영감과 자극이<br>필요한 ';

		#리더십
		elseif ($data['interest_num'] == 13) :
			$subject = '사람과 조직을 효율적으로<br>이끌고 싶은 ';

		#자녀교육
		elseif ($data['interest_num'] == 16) :
			$subject = '좋은 부모가 되고 싶은<br>';

		#지식/상식
		elseif ($data['interest_num'] == 23) :
			$subject = '호기심도 궁금한 것도 많은<br>';

		#시간관리
		elseif ($data['interest_num'] == 29) :
			$subject = '시간을 효율적으로 활용하고 싶은<br>';

		#심리
		elseif ($data['interest_num'] == 24) :
			$subject = '자기와 사람들의 심리에<br>관심이 많은 ';

		#페미니즘
		elseif ($data['interest_num'] == 47) :
			$subject = '좀 더 평등한 세상을 꿈꾸는<br>';

		#글쓰기
		elseif ($data['interest_num'] == 25) :
			$subject = '내일의 작가를 꿈꾸는<br>';

		#요리
		elseif ($data['interest_num'] == 19) :
			$subject = '맛있는 요리에 관심이 많은<br>';

		#역사
		elseif ($data['interest_num'] == 26) :
			$subject = '우리의 지금을 만든<br>역사가 궁금한 ';

		#음악
		elseif ($data['interest_num'] == 31) :
			$subject = '음악을 좋아하는<br>';

		#철학
		elseif ($data['interest_num'] == 20) :
			$subject = '깊이 있는 생각과 통찰을 배우고 싶은<br>';

		#건강
		elseif ($data['interest_num'] == 21) :
			$subject = '오래오래 건강한 삶을 꿈꾸는<br>';

		#공부
		elseif ($data['interest_num'] == 27) :
			$subject = '더 나은 내일을 위해 공부하는<br>';

		#사업
		elseif ($data['interest_num'] == 28) :
			$subject = '멋지게 성공하고 싶은<br>';

		#외국어
		elseif ($data['interest_num'] == 32) :
			$subject = '외국의 언어에 관심이 많은<br>';

		#미술
		elseif ($data['interest_num'] == 34) :
			$subject = '예술과 아름다움에 관심이 많은<br>';

		#과학
		elseif ($data['interest_num'] == 36) :
			$subject = '과학에 관심이 많은<br>';

		#육아
		elseif ($data['interest_num'] == 37) :
			$subject = '좋은 부모가 되고 싶은<br>';

		#정치/사회
		elseif ($data['interest_num'] == 38) :
			$subject = '사회문제와 정치에 관심이 많은<br>';

		#경제
		elseif ($data['interest_num'] == 39) :
			$subject = '경제 변화와 흐름에 관심이 많은<br>';

		#재테크
		elseif ($data['interest_num'] == 48) :
			$subject = '돈 걱정 없이 살고 싶은<br>';

		#관계/소통
		elseif ($data['interest_num'] == 41) :
			$subject = '즐겁고 행복한 관계를 만들고 싶은<br>';

		#자아찾기
		elseif ($data['interest_num'] == 43) :
			$subject = '진짜 나를 찾고 싶은<br>';

		#가족
		elseif ($data['interest_num'] == 45) :
			$subject = '세상에서 가족이 제일 소중한<br>';

		endif;

		if ($subject == '') {
			$subject = $data['interest_name'] . '에 읽으면 좋은 책';
		} else {
			/*
			if($data['age'] && $data['job']) :
				$subject .= $data['age'].'대 '.$data['job']->job_name.'에게 이 책을 추천해요!';
			elseif($data['age']) :
				$subject .= $data['age'].'대에게 이 책을 추천해요!';
			elseif($data['job']) :
				$subject .= $data['job'].'에게 이 책을 추천해요!';
			else :
				$subject .= '당신에게 이 책을 추천해요!';
			endif;
			*/
			$subject .= '당신에게 추천해요!';
		}

		return $subject;
	}


	public function getSexKorString($str)
	{
		$return_str = '';

		if ($str == 'M') {
			$return_str = '남자';
		} else if ($str == 'F') {
			$return_str = '여자';
		}

		return $return_str;
	}

	public function getMarriedKorString($str)
	{
		$return_str = '';

		if ($str == 'solo') {
			//$return_str = '솔로';
			$return_str = '미혼';
		} else if ($str == 'dating') {
			//$return_str = '연애중';
			$return_str = '미혼';
		} else if ($str == 'married') {
			//$return_str = '부부';
			$return_str = '기혼';
		} else if ($str == 'havekids') {
			//$return_str = '가족';
			$return_str = '기혼';
		}

		return $return_str;
	}

	public function getSexText($text)
	{
		if ($text == 'M') {
			return '남자';
		}
		if ($text == 'F') {
			return '여자';
		}

		return '';
	}

	public function getIbookcareCodeList(Request $request, $highLevelCode)
	{
		$dbh = DB::connection('mysql_noprefix');
		$row = $dbh->select("
		SELECT
			x.code, y.additionalDescription AS description
		FROM
			code x
		INNER JOIN
			codeAdditionalInfo y
		ON
			x.code = y.code
		WHERE
			highLevelCode = :highLevelCode
		", ['highLevelCode' => $highLevelCode]);
		return response()->json($row);
	}

	// 임상준 2019-07-25 서초반포도서관 웹사이트에서 책검색시 추천책 목록 보내주기 API
	public function getBookToBook(Request $request, $company_id, $isbn)
	{
		// echo $company_id."_".$isbn;
		$book = \App\Book::where('isbn', $isbn)->first();
		$arr_ret = [];
		$tableName = ($company_id <= 2) ? "fly_library_book" : "fly_library_book_company{$company_id}";

		if (!$book) {
			// 추천책에 없는경우 임의의 책 추천하기
			$str_config_id = "0";
			$str_book_id = "0";
			// 기분 2개
			for ($i = 0; $i < 2; $i++) {
				$feelings = DB::select("
                    Select
                        R.book_id,config_book_feeling_id
                    From
                        fly_book_feeling F
                    Join fly_recom_book R on F.book_id=R.book_id
                    Join " . $tableName . " B on B.book_id=R.book_id
                    Where
                        config_book_feeling_id not in (" . $str_config_id . ")
                        and R.book_id not in (" . $str_book_id . ")
                    Order by rand() limit 1;
                ");
				foreach ($feelings as $feeling) {
					$arr1 = [];
					$arr1['message'] = str_replace("<br>", " ", $this->getFeelingSubject(['feeling_num' =>  $feeling->config_book_feeling_id]));
					$arr1['isbn'] = \App\Book::where('id', $feeling->book_id)->first()->isbn;

					$str_config_id .= "," . $feeling->config_book_feeling_id;
					$str_book_id .= "," . $feeling->book_id;
					array_push($arr_ret, $arr1);
				}
			}
			// 관심사 1개
			$interests = DB::select("select R.book_id,config_book_interest_id from fly_book_interest I join fly_recom_book R on I.book_id=R.book_id join " . $tableName . " B on B.book_id=R.book_id order by rand() limit 1;");       // 관심사 1개
			foreach ($interests as $interest) {
				$arr1 = [];
				$arr1['message'] = str_replace("<br>", " ", $this->getInterestSubject(['interest_num' =>  $interest->config_book_interest_id]));
				$arr1['isbn'] = \App\Book::where('id', $interest->book_id)->select('isbn')->first()->isbn;
				array_push($arr_ret, $arr1);
			}
			// array_push($arr_ret,['debug'=>1]);
		} else {
			$recom_book = \App\RecomBook::where('book_id', $book->id)->first();
			if (!$recom_book) {
				// 추천책에 없는경우 임의의 책 추천하기
				$str_config_id = "0";
				$str_book_id = "0";
				// 기분 2개
				for ($i = 0; $i < 2; $i++) {
					$feelings = DB::select("
                        Select
                            R.book_id,config_book_feeling_id
                        From
                            fly_book_feeling F
                        Join fly_recom_book R on F.book_id=R.book_id
                        Join " . $tableName . " B on B.book_id=R.book_id
                        Where
                            config_book_feeling_id not in (" . $str_config_id . ")
                            and R.book_id not in (" . $str_book_id . ")
                        Order by rand() limit 1;
                    ");
					foreach ($feelings as $feeling) {
						$arr1 = [];
						$arr1['message'] = str_replace("<br>", " ", $this->getFeelingSubject(['feeling_num' =>  $feeling->config_book_feeling_id]));
						$arr1['isbn'] = \App\Book::where('id', $feeling->book_id)->first()->isbn;

						$str_config_id .= "," . $feeling->config_book_feeling_id;
						$str_book_id .= "," . $feeling->book_id;
						array_push($arr_ret, $arr1);
					}
				}
				// 관심사 1개
				$interests = DB::select("select R.book_id,config_book_interest_id from fly_book_interest I join fly_recom_book R on I.book_id=R.book_id join " . $tableName . " B on B.book_id=R.book_id order by rand() limit 1;");       // 관심사 1개
				foreach ($interests as $interest) {
					$arr1 = [];
					$arr1['message'] = str_replace("<br>", " ", $this->getInterestSubject(['interest_num' =>  $interest->config_book_interest_id]));
					$arr1['isbn'] = \App\Book::where('id', $interest->book_id)->select('isbn')->first()->isbn;
					array_push($arr_ret, $arr1);
				}
				// array_push($arr_ret,['debug'=>2]);
			} else {
				// 추천책에 있는 경우
				$feelings = DB::select("Select book_id,config_book_feeling_id from fly_book_feeling where book_id = " . $book->id . " order by rand() limit 1");     // 기분 가져오기
				$interests = DB::select("Select book_id,config_book_interest_id from fly_book_interest where book_id = " . $book->id);     // 관심사 가져오기

				$str_config_id = "0";
				$str_book_id = "0";

				// 기분 1개
				if (count($feelings) > 0) {
					$feelings = DB::select("
                        Select
                            R.book_id,config_book_feeling_id
                        from
                            fly_book_feeling I
                        join
                            fly_recom_book R on I.book_id=R.book_id
                        join " . $tableName . " B on B.book_id=R.book_id
                        where config_book_feeling_id = " . $feelings[0]->config_book_feeling_id . "
                        order by rand() limit 1;
                    ");       // 관심사 1개
					foreach ($feelings as $feeling) {
						$arr1 = [];
						$arr1['message'] = str_replace("<br>", " ", $this->getFeelingSubject(['feeling_num' =>  $feeling->config_book_feeling_id]));
						$arr1['isbn'] = \App\Book::where('id', $feeling->book_id)->first()->isbn;
						$str_config_id .= "," . $feeling->config_book_feeling_id;
						$str_book_id .= "," . $feeling->book_id;
						array_push($arr_ret, $arr1);
					}
				}

				// 관심사 1개
				if (count($interests) > 0) {
					$interests = DB::select("
                        Select
                            R.book_id,config_book_interest_id
                        from
                            fly_book_interest I
                        join
                            fly_recom_book R on I.book_id=R.book_id
                        join " . $tableName . " B on B.book_id=R.book_id
                        where config_book_interest_id = " . $interests[0]->config_book_interest_id . "
                        order by rand() limit 1;
                    ");       // 관심사 1개
					foreach ($interests as $interest) {
						$arr1 = [];
						$arr1['message'] = str_replace("<br>", " ", $this->getInterestSubject(['interest_num' =>  $interest->config_book_interest_id]));
						$arr1['isbn'] = \App\Book::where('id', $interest->book_id)->select('isbn')->first()->isbn;
						array_push($arr_ret, $arr1);
					}
				}

				// 부족한수 만큼 내용 채우기
				$cnt = 3 - count($arr_ret);
				for ($i = 0; $i < $cnt; $i++) {
					$sql = "
                        Select
                            R.book_id,config_book_feeling_id
                        From
                            fly_book_feeling F
                        Join fly_recom_book R on F.book_id=R.book_id
                        Join " . $tableName . " B on B.book_id=R.book_id
                        Where
                            config_book_feeling_id not in (" . $str_config_id . ")
                            and R.book_id not in (" . $str_book_id . ")
                        Order by rand() limit 1;
                    ";
					// array_push($arr_ret,['sql'=>$sql]);
					$feelings = DB::select($sql);
					foreach ($feelings as $feeling) {
						$arr1 = [];
						$arr1['message'] = str_replace("<br>", " ", $this->getFeelingSubject(['feeling_num' =>  $feeling->config_book_feeling_id]));
						$arr1['isbn'] = \App\Book::where('id', $feeling->book_id)->first()->isbn;

						$str_config_id .= "," . $feeling->config_book_feeling_id;
						$str_book_id .= "," . $feeling->book_id;
						array_push($arr_ret, $arr1);
					}
				}

				// array_push($arr_ret,['debug'=>3]);
			}
		}

		return response()->json($arr_ret);
	}

	// 서초 구립 도서관 회원 나이, 성별에 맞는 추천책 3권 (로그인 후)
	public function getAgeGenderBook(Request $request, $company_id, $gender, $age)
	{

		$company_id = 1; //서초 구립 도서관

		if (!$age || !$gender) {
			return response()->json(['error' => '필수 파라미터 값이 없습니다.'], 400, array(), JSON_PRETTY_PRINT);
		}

		if ($age % 10 != 0) {
			return response()->json(['error' => '필수 파라미터 값이 잘못되었습니다.'], 400, array(), JSON_PRETTY_PRINT);
		}
		$config_book_age_id = (int) $age / 10;

		$result_arr = [];

		$join_table = ($company_id <= 2) ? "library_book" : "library_book_company{$company_id}";
		// 나이, 성별 관련 추천책 3권
		$isbn_arr = DB::table('book_age')
			->join("{$join_table}", 'book_age.book_id', '=', "{$join_table}.book_id")
			->where('config_book_age_id', $config_book_age_id)
			->inRandomOrder()
			->limit(1000)
			->pluck("{$join_table}.isbn")->toArray();

		if (empty($isbn_arr)) {
			return null;
		}

		$isbn_arr = join(",", $isbn_arr);

		if ($gender == 'M') {
			$user_gender = '남성';
		} else if ($gender == 'F') {
			$user_gender = '남성';
		} else {
			return response()->json(['error' => '필수 파라미터 값이 잘못되었습니다.'], 400, array(), JSON_PRETTY_PRINT);
		}

		$book_gender = DB::select(DB::raw("SELECT * FROM bookFeatureMergeByISBN WHERE (isbn IN (" . $isbn_arr . ")) AND (sex like '%" . $user_gender . "%') order by rand() limit 3"));

		if (empty($book_gender)) {
			return null;
		}
		$i = 0;
		foreach ($book_gender as $book) {
			$book_gender_arr = [];
			if ($i == 0)	$book_gender_arr['message'] = $age . "대에서 많이 읽는 책이에요!";
			if ($i == 1) {
				if ($book->feeling) {
					$book_gender_arr['message'] = $age . "대 " . $user_gender . "이 " . $book->feeling . " 읽으면 좋은 책이에요!";
				} else {
					$book_gender_arr['message'] = $age . "대 " . $user_gender . "이 " . $book->feeling . "읽으면 좋은 책이에요!";
				}
			}
			if ($i == 2)	$book_gender_arr['message'] = $age . "대 " . $user_gender . "이 " . $book->interest . "에 관심 있을 때 읽으면 좋은 책이에요!";
			$i++;
			$book_gender_arr['isbn'] = $book->isbn;
			array_push($result_arr, $book_gender_arr);
		}

		if (empty($result_arr)) {
			return null;
		}

		return response()->json($result_arr, 200, array(), JSON_PRETTY_PRINT);
	}
}
