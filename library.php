<?php
/**
설문등록
**/
Route::post('recombook', 'Library\RecombookController@store');

/**
설문에 따른 추천책 받기
**/
Route::get('recombook/{recombook}', 'Library\RecombookController@show');
Route::get('childRecombook/{requestSeq}', 'Library\RecombookController@getChildRecombook');
Route::get('survey/{survey}', 'Library\RecombookController@showSurvey');
/**
책정보
**/
Route::get('book/{book}', 'Library\BookController@show');
Route::get('book/{book}/loan', 'Library\BookController@isLoan');

Route::get('recombook2', 'Library\RecombookController@makeRecombook2');
/**
전달
**/
Route::post('recombook/{recombook}/send_phone', 'Library\RecombookController@sendPhone');

Route::get('/ibookcareCodeList/{highLevelCode}', 'Library\RecombookController@getIbookcareCodeList');

/** 
추가 콘텐츠 뷰
**/
// 이럴 땐 뭐읽지
Route::get('yozm', 'YozmController@index'); // 이럴 땐 뭐읽지 전체 리스트
Route::get('yozm30', 'Library\ContentsController@yozm30'); // 이럴 땐 뭐읽지 전체 리스트 30개
Route::get('yozm/{yozm}', 'Library\ContentsController@show'); // 이럴 땐 뭐읽지 상세
// 요즘 어때요
Route::get('manual-recombook', 'ManualRecomBookController@getConfigRecomList');
Route::get('manual-recombook/{recom}', 'ManualRecomBookController@getConfigRecomDetailList');
Route::get('manual-recombook/{recom}/{detail}', 'Library\ContentsController@getManualRecomBook');
// 서초반포도서관 웹사이트에서 책검색시 추천책 목록 보내주기 API
Route::get('/{company_id}/recom/{isbn}', 'Library\RecombookController@getBookToBook');
// 서초반포도서관 웹사이트에서 회원정보(성별,나이)에 따른 추천책 목록 보내주기
Route::get('/recom/{company_id}/{gender}/{age}', 'Library\RecombookController@getAgeGenderBook');

// 스크린 광고
Route::get('screeninfo/{type}', 'v2\AdController@get'); // type에 따른 광고 가져오기
Route::get('screeninfo/content/{ad_id}', 'v2\AdController@content'); // 광고의 더보기 버튼의 콘텐츠 가져오기