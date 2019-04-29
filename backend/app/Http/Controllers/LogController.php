<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;

class LogController extends Controller
{
    //
    use Result,Tools;

    public function index(Request $request)
    {
        /*
        paginate 方法根据用户浏览的当前页码，自动设置恰当的偏移量offset和限制数limit。默认情况下，HTTP请求中, URL参数的 page 的值被检测作为页码。 当然， Laravel也会自动检测这个值，并自动插入到分页器生成的链接中。
        传递给 paginate 方法的唯一参数是你希望"每页"展示的项目数量.
        */
        $pageSize = $request->input('pageSize', 10);
        $page = $request->input('page', 1);
        $data = DB::table('log_logins')->select(['id', 'user_name', 'type', 'desc'])
            ->when(!$this->isAdmin(), function($query) {//当当前用户不为admin,则where增加过滤条件,只显示当前用户的登录记录
                return $query->where('user_id', Auth::user()->id);
            })
            ->latest()->paginate($pageSize);
        return Response()->json($data);
    }

    // 操作日志记录
    public function show(Request $request){
        $pageSize = $request->input('pageSize', 10);
        $page = $request->input('page', 1);
        $data = DB::table('log_works')->select(['id', 'user_name', 'type', 'desc'])
            ->when(!$this->isAdmin(), function($query) {
                return $query->where('user_id', Auth::user()->id);
            })
            ->paginate($pageSize);
        return Response()->json($data);
    }

}
