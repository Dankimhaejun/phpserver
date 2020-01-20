<?php

namespace App\Http\Controllers\Library;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Validator;

class ContentsController extends Controller
{
    public function __construct()
    {
    }


	// 도서관 이럴 때 뭐읽지 상세 리스트
	public function show(Request $request)
	{
		$yozm_id = $request->yozm;
		$yozm = \App\Yozm::select('id', 'title_img')->findOrFail($yozm_id);
		$yozm->is_favorite = $yozm->isFavorite();
		$yozm->share_url = 'http://www.flybook.kr/yozm/'.$yozm_id;
		$yozm->share_img = env('IMAGES_URL').'/yozm/'.$yozm->title_img;
		$yozm->contents;
		foreach($yozm->contents as $content) {
			$content->book;
			$tmparr = explode('|', $content->book->author);
			if(count($tmparr)>1) {
				$content->book->author = $tmparr[0].' 외 '.(count($tmparr)-1).'명';
			}
			else {
				$content->book->author = $tmparr[0];
			}
			$content->mobile_title_html = nl2br($content->mobile_title);
			unset($content->mobile_title);
			// $content->mobile_img = env('IMAGES_URL').'/yozm_mobile_content/'.$content->mobile_img;

			$content->book->book_img = $content->book->book_img;

		}
		unset($yozm->title_img);
		return response()->json($yozm);
	}

	public function getManualRecomBook(Request $request)
	{
		$recom_detail_id = $request->detail;
		$orderby = $request->input('orderby', 'popular');
		$config = \App\ConfigManualRecomDetail::findOrFail($recom_detail_id);
		$recom = \App\ConfigManualRecom::where('id', $config->config_manual_recom_id)->first();
		$id = $config->id;

		$query = \App\Book::select('id', 'title', 'publisher', 'author', 'book_img')
			->join('book_count', 'book.id', '=', 'book_count.book_id', 'left')
			->where('is_manual_recom_except', 'N');

		if($orderby == 'review') $query->orderBy('cnt_review', 'desc');
		else if($orderby == 'latest') $query->orderBy('pubdate', 'desc');
		else $query->orderBy('cnt_book_read', 'desc');

		if($id == 1) {
			$query->whereHas('feelings', function($query) {
				$query->whereIn('config_book_feeling_id', [3, 14, 23]);
			});
		}
		else if($id >= 2 && $id <= 28 || $id == 32 || $id == 37 || $id == 39 || $id == 55 || $id == 57) {
			if($config->feeling_id) {
				$query->whereHas('feelings', function($query) use ($config) {
					$query->where('config_book_feeling_id', $config->feeling_id);
				});
			}
			if($config->interest_id) {
				$query->whereHas('interests', function($query) use ($config) {
					$query->where('config_book_interest_id', $config->interest_id);
				});
			}
			if($config->book_tag) {
				$query->whereHas('tags', function($query) use ($config) {
					$query->where('tag', $config->book_tag);
				});
			}
		}
		else if($id == 29) {
			$query->where(function ($query) use ($config) {
				$query->whereHas('feelings', function($query) use ($config) {
					$query->where('config_book_feeling_id', $config->feeling_id);
				});
			})
			->orWhere(function ($query) use ($config) {
				$query->whereHas('tags', function($query) use ($config) {
					$query->where('tag', $config->book_tag);
				});
			});
		}
		else if($id == 30 || $id == 31) {
			$query->where('genre', 'literature');
			$query->whereHas('feelings', function($query) {
				$query->where('config_book_feeling_id', 23);
			});
		}
		else if($id == 33) {
			$query->whereHas('tags', function($query) {
				$query->where('tag', '우정')
					->orWhere('tag', '친구');
			});
		}
		else if($id == 34) {
			$query->where(function ($query) {
				$query->whereHas('feelings', function($query) {
					$query->where('config_book_feeling_id', 21);
				});
			})
			->orWhere(function ($query) {
				$query->whereHas('tags', function($query) {
					$query->where('tag', '책맥');
				});
			});
		}
		else if($id == 35) {
			$query->whereHas('tags', function($query) {
				$query->where('tag', '집정리')
					->orWhere('tag', '미니멀리스트');
			});
		}
		else if($id == 36) {
			$query->whereHas('tags', function($query) {
				$query->where('tag', '자기계발');
			});
		}
		else if($id == 38) {
			$query->where('genre', 'literature');
			$query->whereHas('interests', function($query) {
				$query->where('config_book_interest_id', 45);
			});
		}
		else if($id == 40) {
			$query->whereHas('tags', function($query) {
				$query->where('tag', '프레젠테이션');
			});
		}
		else if($id == 41) {
			$query->whereHas('tags', function($query) {
				$query->where('tag', '회의');
			});
		}
		else if($id == 42) {
			$query->whereHas('jobs', function($query) {
				$query->where('config_book_job_id', 2);
			});
			$query->whereHas('interests', function($query) {
				$query->where('config_book_interest_id', 41);
			});
		}
		else if($id == 43) {
			$query->whereHas('jobs', function($query) {
				$query->where('config_book_job_id', 2);
			});
			$query->whereHas('interests', function($query) {
				$query->where('config_book_interest_id', 13);
			});
		}
		else if($id == 44) {
			$query->whereHas('jobs', function($query) {
				$query->where('config_book_job_id', 2);
			});
			$query->where(function ($query) {
				$query->whereHas('interests', function($query) {
					$query->where('config_book_interest_id', 32);
				});
			})
			->orWhere(function ($query) {
				$query->whereHas('tags', function($query) {
					$query->where('tag', '영어');
				});
			});
		}
		else if($id == 45) {
			$query->whereHas('jobs', function($query) {
				$query->where('config_book_job_id', 2);
			});
			$query->whereHas('interests', function($query) {
				$query->where('config_book_interest_id', 46);
			});
		}
		else if($id == 46) {
			$query->whereHas('jobs', function($query) {
				$query->where('config_book_job_id', 2);
			});
			$query->whereHas('interests', function($query) {
				$query->where('config_book_interest_id', 28);
			});
		}
		else if($id == 47) {
			$query->whereHas('jobs', function($query) {
				$query->where('config_book_job_id', 2);
			});
			$query->where(function ($query) {
				$query->whereHas('tags', function($query) {
					$query->where('tag', '화가났을때');
				});
			})
			->orWhere(function ($query) {
				$query->whereHas('tags', function($query) {
					$query->where('tag', '스트레스');
				});
			})
			->orWhere(function ($query) {
				$query->whereHas('tags', function($query) {
					$query->where('tag', '사표');
				});
			});
		}
		else if($id == 48) {
			$query->where(function ($query) {
				$query->whereHas('tags', function($query) {
					$query->where('tag', '자아성찰');
				});
			})
			->orWhere(function ($query) {
				$query->whereHas('interests', function($query) {
					$query->where('config_book_interest_id', 43);
				});
			});
		}
		else if($id == 49) {
			$query->where(function ($query) {
				$query->whereHas('feelings', function($query) {
					$query->where('config_book_feeling_id', 21);
				});
			})
			->orWhere(function ($query) {
				$query->whereHas('tags', function($query) {
					$query->where('tag', '졸린책');
				});
			});
		}
		else if($id == 50) {
			$query->whereHas('tags', function($query) {
				$query->where('tag', '감성');
			});
		}
		else if($id == 51) {
			$query->where('genre', 'literature');
			$query->whereHas('tags', function($query) {
				$query->where('tag', '세상');
			});
		}
		else if($id == 52) {
			$query->whereHas('feelings', function($query) {
				$query->where('config_book_feeling_id', 21);
			});
			$query->whereHas('tags', function($query) {
				$query->where('tag', '공포');
			});
		}
		else if($id == 53) {
			$query->whereHas('feelings', function($query) {
				$query->where('config_book_feeling_id', 8);
			});
			$query->whereHas('tags', function($query) {
				$query->where('tag', '추억');
			});
		}
		else if($id == 54) {
			$query->where(function ($query) {
				$query->whereHas('feelings', function($query) {
					$query->whereIn('config_book_feeling_id', [3, 23]);
				});
			})
			->orWhere(function ($query) {
				$query->whereHas('tags', function($query) {
					$query->where('tag', '감성');
				});
			});
		}
		else if($id == 56) {
			$query->whereHas('tags', function($query) {
				$query->where('tag', '귀여운책')
					->orWhere('tag', '예쁜표지');
			});
		}

		else if($id == 58) {
			$query->whereHas('tags', function($query) {
				$query->where('tag', '소개팅')
					->orWhere('tag', '연애화술');
			})
			->orWhereHas('admin_tags', function($query) {
				$query->where('tag', '소개팅')
					->orWhere('tag', '연애화술');
			});
		}
		else if($id == 59) {
			$query->whereHas('tags', function($query) {
				$query->where('tag', '연애소설')
					->orWhere('tag', '감성');
			})
			->orWhereHas('admin_tags', function($query) {
				$query->where('tag', '연애소설')
					->orWhere('tag', '감성');
			});
		}
		else if($id == 60) {
			$query->whereHas('tags', function($query) {
				$query->where('tag', '쓸쓸');
			})
			->orWhereHas('admin_tags', function($query) {
				$query->where('tag', '쓸쓸');
			});
		}
		else if($id == 61) {
			$query->whereHas('tags', function($query) {
				$query->where('tag', '고백');
			})
			->orWhereHas('admin_tags', function($query) {
				$query->where('tag', '고백');
			});
		}
		else if($id == 62) {
			$query->whereHas('tags', function($query) {
				$query->where('tag', '시집');
			})
			->orWhereHas('admin_tags', function($query) {
				$query->where('tag', '시집');
			});
		}
		else if($id == 63) {
			$query->whereHas('tags', function($query) {
				$query->where('tag', '연애기술')
					->orWhere('tag', '연애스킬');
			})
			->orWhereHas('admin_tags', function($query) {
				$query->where('tag', '연애기술')
					->orWhere('tag', '연애스킬');
			})
			->orWhere(function ($query) {
				$book_id_arr = \App\YozmContent::where('id', 413)->pluck('book_id')->toArray();
				$query->whereIn('id', $book_id_arr);
			});
		}
		else if($id == 64) {
			$query->whereHas('tags', function($query) {
				$query->where('tag', '영어')
					->orWhere('tag', '회화')
					->orWhere('tag', '토익');
			})
			->orWhereHas('admin_tags', function($query) {
				$query->where('tag', '영어')
					->orWhere('tag', '회화')
					->orWhere('tag', '토익');
			});
		}
		else if($id == 65) {
			$query->whereHas('tags', function($query) {
				$query->where('tag', '자소서')
					->orWhere('tag', '이력서');
			})
			->orWhereHas('admin_tags', function($query) {
				$query->where('tag', '자소서')
					->orWhere('tag', '이력서');
			});
		}
		else if($id == 66) {
			$query->whereHas('tags', function($query) {
				$query->where('tag', '고전')
					->orWhere('tag', '인문학');
			})
			->orWhereHas('admin_tags', function($query) {
				$query->where('tag', '고전')
					->orWhere('tag', '인문학');
			});
		}
		else if($id == 67) {
			$query->whereHas('tags', function($query) {
				$query->where('tag', '면접');
			})
			->orWhereHas('tags', function($query) {
				$book_id_arr = \App\BookTag::where('tag', '비즈니스')->pluck('book_id')->toArray();
				$query->where('tag', '화술')
					->whereIn('book_id', $book_id_arr);
			})
			->orWhereHas('admin_tags', function($query) {
				$query->where('tag', '면접');
			})
			->orWhereHas('admin_tags', function($query) {
				$book_id_arr = \App\BookAdminTag::where('tag', '비즈니스')->pluck('book_id')->toArray();
				$query->where('tag', '화술')
					->whereIn('book_id', $book_id_arr);
			});
		}
		else if($id == 68) {
			$query->whereHas('tags', function($query) {
				$query->where('tag', '몸짱')
					->orWhere('tag', '헬스')
					->orWhere('tag', '다이어트');
			})
			->orWhereHas('admin_tags', function($query) {
				$query->where('tag', '몸짱')
					->orWhere('tag', '헬스')
					->orWhere('tag', '다이어트');
			});
		}
		else if($id == 69) {
			$query->whereHas('tags', function($query) {
				$book_id_arr = \App\BookTag::where('tag', '트렌드')->pluck('book_id')->toArray();
				$query->where('tag', '미래')->whereIn('book_id', $book_id_arr);
			})
			->orWhereHas('admin_tags', function($query) {
				$book_id_arr = \App\BookAdminTag::where('tag', '트렌드')->pluck('book_id')->toArray();
				$query->where('tag', '미래')->whereIn('book_id', $book_id_arr);
			});
		}
		else if($id == 70) {
			$query->whereHas('tags', function($query) {
				$query->whereIn('tag', ['단편', '단편소설', '단편집', '짧은소설']);
			})
			->orWhereHas('admin_tags', function($query) {
				$query->whereIn('tag', ['단편', '단편소설', '단편집', '짧은소설']);
			});
		}
		else if($id == 71) {
			$query->whereHas('tags', function($query) {
				$query->whereIn('tag', ['재테크']);
			})
			->orWhereHas('admin_tags', function($query) {
				$query->whereIn('tag', ['재테크']);
			});
		}
		else if($id == 72) {
			$query->whereHas('tags', function($query) {
				$query->whereIn('tag', ['시사상식', '세상']);
			})
			->orWhereHas('admin_tags', function($query) {
				$query->whereIn('tag', ['시사상식', '세상']);
			});
		}
		else if($id == 73) {
			$query->whereHas('tags', function($query) {
				$query->whereIn('tag', ['외국어', '영어']);
			})
			->orWhereHas('admin_tags', function($query) {
				$query->whereIn('tag', ['외국어', '영어']);
			});
		}
		else if($id == 74) {
			$query->whereHas('tags', function($query) {
				$query->whereIn('tag', ['재미', '스트레스', '통쾌', '유쾌']);
			})
			->orWhereHas('admin_tags', function($query) {
				$query->whereIn('tag', ['재미', '스트레스', '통쾌', '유쾌']);
			});
		}
		else if($id == 75) {
			$query->whereHas('tags', function($query) {
				$query->whereIn('tag', ['그림책', '만화책', '웹툰']);
			})
			->orWhereHas('admin_tags', function($query) {
				$query->whereIn('tag', ['그림책', '만화책', '웹툰']);
			});
		}
		else if($id == 76) {
			$query->whereHas('tags', function($query) {
				$query->whereIn('tag', ['희망']);
			})
			->orWhereHas('admin_tags', function($query) {
				$query->whereIn('tag', ['희망']);
			});
		}
		else if($id == 77) {
			$query->whereHas('tags', function($query) {
				$query->whereIn('tag', ['위로']);
			})
			->orWhereHas('admin_tags', function($query) {
				$query->whereIn('tag', ['위로']);
			});
		}
		else if($id == 78) {
			$query->whereHas('tags', function($query) {
				$query->whereIn('tag', ['휴식']);
			})
			->orWhereHas('admin_tags', function($query) {
				$query->whereIn('tag', ['휴식']);
			});
		}
		else if($id == 79) {
			$query->whereHas('tags', function($query) {
				$query->whereIn('tag', ['힐링요리', '간편요리', '집밥']);
			})
			->orWhereHas('admin_tags', function($query) {
				$query->whereIn('tag', ['힐링요리', '간편요리', '집밥']);
			});
		}
		else if($id == 80) {
			$query->whereHas('tags', function($query) {
				$query->whereIn('tag', ['가족']);
			})
			->orWhereHas('admin_tags', function($query) {
				$query->whereIn('tag', ['가족']);
			});
		}
		else if($id == 81) {
			$query->whereHas('tags', function($query) {
				$query->whereIn('tag', ['컬러링북', '멍때리기']);
			})
			->orWhereHas('admin_tags', function($query) {
				$query->whereIn('tag', ['컬러링북', '멍때리기']);
			});
		}
		else if($id == 82) {
			$query->whereHas('tags', function($query) {
				$query->whereIn('tag', ['여행']);
			})
			->orWhereHas('admin_tags', function($query) {
				$query->whereIn('tag', ['여행']);
			});
		}
		else if($id == 83) {
			$query->whereHas('tags', function($query) {
				$query->whereIn('tag', ['다이어트', '식단']);
			})
			->orWhereHas('admin_tags', function($query) {
				$query->whereIn('tag', ['다이어트', '식단']);
			});
		}
		else if($id == 84) {
			$query->whereHas('tags', function($query) {
				$query->whereIn('tag', ['퇴사', '취업', '이직']);
			})
			->orWhereHas('admin_tags', function($query) {
				$query->whereIn('tag', ['퇴사', '취업', '이직']);
			});
		}
		else if($id == 85) {
			$query->whereHas('tags', function($query) {
				$query->whereIn('tag', ['인테리어', '셀프인테리어']);
			})
			->orWhereHas('admin_tags', function($query) {
				$query->whereIn('tag', ['인테리어', '셀프인테리어']);
			});
		}
		else if($id == 86) {
			$query->whereHas('tags', function($query) {
				$query->whereIn('tag', ['연말정산', '세금', '절세']);
			})
			->orWhereHas('admin_tags', function($query) {
				$query->whereIn('tag', ['연말정산', '세금', '절세']);
			});
		}
		else if($id == 87) {
			$query->whereHas('tags', function($query) {
				$query->whereIn('tag', ['핸드메이드', 'DIY선물']);
			})
			->orWhereHas('admin_tags', function($query) {
				$query->whereIn('tag', ['핸드메이드', 'DIY선물']);
			});
		}
		else if($id == 88) {
			$query->whereHas('tags', function($query) {
				$query->whereIn('tag', ['연애']);
			})
			->orWhereHas('admin_tags', function($query) {
				$query->whereIn('tag', ['연애']);
			});
		}
		else if($id == 89) {
			$query->whereHas('tags', function($query) {
				$query->whereIn('tag', ['글쓰기']);
			})
			->orWhereHas('admin_tags', function($query) {
				$query->whereIn('tag', ['글쓰기']);
			});
		}
		else if($id == 90) {
			$query->whereHas('tags', function($query) {
				$query->whereIn('tag', ['동남아', '사막여행']);
			})
			->orWhereHas('admin_tags', function($query) {
				$query->whereIn('tag', ['동남아', '사막여행']);
			});
		}
		else if($id == 91) {
			$query->whereHas('tags', function($query) {
				$query->whereIn('tag', ['고전']);
			})
			->orWhereHas('admin_tags', function($query) {
				$query->whereIn('tag', ['고전']);
			});
		}
		else if($id == 92) {
			$query->whereHas('tags', function($query) {
				$query->whereIn('tag', ['공부']);
			})
			->orWhereHas('admin_tags', function($query) {
				$query->whereIn('tag', ['공부']);
			});
		}
		else if($id == 93) {
			$query->whereHas('tags', function($query) {
				$query->whereIn('tag', ['창업']);
			})
			->orWhereHas('admin_tags', function($query) {
				$query->whereIn('tag', ['창업']);
			});
		}
		else if($id == 95) {
			$query->whereHas('tags', function($query) {
				$query->whereIn('tag', ['여행', '배낭여행']);
			})
			->orWhereHas('admin_tags', function($query) {
				$query->whereIn('tag', ['여행', '배낭여행']);
			});
		}
		else if($id == 97) {
			$query->whereHas('tags', function($query) {
				$query->whereIn('tag', ['퇴사', '직장']);
			})
			->orWhereHas('admin_tags', function($query) {
				$query->whereIn('tag', ['퇴사', '직장']);
			});
		}
		else if($id == 99) {
			$query->whereHas('tags', function($query) {
				$query->whereIn('tag', ['조언']);
			})
			->orWhereHas('admin_tags', function($query) {
				$query->whereIn('tag', ['조언']);
			});
		}
		else if($id == 100) {
			$query->whereHas('tags', function($query) {
				$query->whereIn('tag', ['새출발']);
			})
			->orWhereHas('admin_tags', function($query) {
				$query->whereIn('tag', ['새출발']);
			});
		}
		else if($id == 101) {
			$query->whereHas('tags', function($query) {
				$query->whereIn('tag', ['집정리']);
			})
			->orWhereHas('admin_tags', function($query) {
				$query->whereIn('tag', ['집정리']);
			});
		}
		else if($id == 102) {
			$query->whereHas('tags', function($query) {
				$query->whereIn('tag', ['추억']);
			})
			->orWhereHas('admin_tags', function($query) {
				$query->whereIn('tag', ['추억']);
			});
		}
		else if($id == 103) {
			$query->whereHas('tags', function($query) {
				$query->whereIn('tag', ['습관']);
			})
			->orWhereHas('admin_tags', function($query) {
				$query->whereIn('tag', ['습관']);
			});
		}
		else if($id == 104) {
			$query->whereHas('tags', function($query) {
				$query->whereIn('tag', ['고양이']);
			})
			->orWhereHas('admin_tags', function($query) {
				$query->whereIn('tag', ['고양이']);
			});
		}
		else if($id == 105) {
			$query->whereHas('tags', function($query) {
				$query->whereIn('tag', ['목표']);
			})
			->orWhereHas('admin_tags', function($query) {
				$query->whereIn('tag', ['목표']);
			});
		}


		$list = $query->take(10)->get();

		foreach($list as $item) {
			$item->keyword1 = $recom->text1;
			$item->keyword2 = trim($config->text1);
			$item->recom_title = $recom->text1.' '.$config->text1.$recom->text2;
			$item->book_img = $item->book_img;
		}

		return response()->json($list);
	}

	// 키오스크 요즘 어때요 30개씩
    public function yozm30(Request $request)
    {
		$query = \App\Yozm::select('id', 'mobile_title', 'mobile_main_img', 'mobile_img')->orderBy('id', 'desc');

		if($request->filled('keyword')) {
			$query->where('title', 'like', '%'.$request->keyword.'%');
		}

		$yozms = $query->paginate(30);

		foreach($yozms as $yozm) {
			$yozm->mobile_title_html = nl2br($yozm->mobile_title);
			$yozm->mobile_main_img = env('IMAGES_URL').'/yozm_main_mobile/'.$yozm->mobile_main_img;
			$yozm->mobile_img = env('IMAGES_URL').'/yozm_mobile/'.$yozm->mobile_img;
			$yozm->is_favorite = $yozm->isFavorite();
		}
		return response()->json($yozms);
    }
}
