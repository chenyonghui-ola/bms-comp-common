# 常用函数使用示例

array_add()
array_add 函数添加给定键值对到数组 —— 如果给定键不存在的话：
$array = array_add(['name' => 'Desk'], 'price', 100);
// ['name' => 'Desk', 'price' => 100]

array_divide()
array_divide 函数返回两个数组，一个包含原数组的所有键，另外一个包含原数组值
list($keys, $values) = array_divide($array);

array_dot()
array_dot 函数使用”.”号将将多维数组转化为一维数组
$array = array('foo' => array('bar' => 'baz'));
$array = array_dot($array);
// array('foo.bar' => 'baz');

array_except()
array_except 函数从数组中移除给定键值对
array_except($array, array('keys', 'to', 'remove'));

array_first()
$array = [100, 200, 300];
$value = array_first($array, function ($value, $key) {
return $value >= 150;
});
// 200

array_flatten()
array_flatten 函数将多维数组转化为一维数组：
$array = ['name' => 'Joe', 'languages' => ['PHP', 'Ruby']];
$array = array_flatten($array);
// ['Joe', 'PHP', 'Ruby'];

array_forget()
array_forget 函数使用”.”号从嵌套数组中移除给定键值对：
$array = ['products' => ['desk' => ['price' => 100]]];
array_forget($array, 'products.desk');
// ['products' => []]

array_has()
array_has 函数使用“.”检查给定数据项是否在数组中存在：
$array = ['product' => ['name' => 'desk', 'price' => 100]];
$hasItem = array_has($array, 'product.name');

array_last()
array_last 函数返回通过过滤数组的最后一个元素：
$array = [100, 200, 300, 110];
$value = array_last($array, function ($value, $key) {
return $value >= 150;
});
// 300

array_only()
array_only 方法只从给定数组中返回指定键值对：
$array = ['name' => 'Desk', 'price' => 100, 'orders' => 10];
$array = array_only($array, ['name', 'price']);
// ['name' => 'Desk', 'price' => 100]

array_random()
array_random 函数从数组中返回随机值：
$array = [1, 2, 3, 4, 5];
$random = array_random($array);
// 4 - (retrieved randomly)

array_sort()
多维数组排序，通过指定key排序
array_sort($arr, $key, SORT_ASC);

array_sort_recursive()
array_sort_recursive 函数使用 sort 函数对数组进行递归排序：
$array = [
['Roman', 'Taylor', 'Li'],
['PHP', 'Ruby', 'JavaScript'],
];
$array = array_sort_recursive($array);
/*
[
['Li', 'Roman', 'Taylor'],
['JavaScript', 'PHP', 'Ruby'],
]
*/

array_where()
array_where 方法会根据给定的闭包对数组进行过滤：
$array = [100, '200', 300, '400', 500];
$array = array_where($array, function ($key, $value) {
return is_string($value);
});
// [1 => '200', 3 => '400']

array_wrap()
array_wrap 用于将非数组转化成数组形式
array_wrap(2);
// [2]

head()
head 函数只是简单返回给定数组的第一个元素：
$array = [100, 200, 300];
$first = head($array);
// 100

last()
last 方法返回所给定数组中的最后一个元素：
$array = [100, 200, 300];
$last = last($array);
// 300

blank()
blank 函数判断给定的值是否为「空」
blank('');
blank('   ');
blank(null);
blank(collect());
// true

blank(0);
blank(true);
blank(false);
// false

retry()
retry 函数尝试执行给定的回调，直到达到给定的最大尝试阈值。如果回调没有抛出异常，回调返回值将被返回。如果回调抛出异常，将自动重试。达到最大尝试次数，将抛出异常：
return retry(5, function () {
// Attempt 5 times while resting 100ms in between attempts...
}, 100);

snake_case()
驼峰转化成连接符连接
$converted = snake_case('fooBar');
// foo_bar

studly_case()
studly_case函数将给定字符串转化为单词开头字母大写的格式：
$value = studly_case('foo_bar');
// FooBar

camel_case()
返回驼峰格式,首字母小写
$value = camel_case('foo_bar');
// fooBar

starts_with()
函数判断给定的字符串的开头是否是指定值
$result = starts_with('This is my name', 'This');
// true

str_after()
函数返回在字符串中指定值之后的所有内容
$slice = str_after('This is my name', 'This is');
// ' my name'

str_before()
函数返回在字符串中指定值之前的所有内容
str_before('This is my name', 'my name');
// 'This is '

str_contains()
str_contains函数判断给定字符串是否包含给定值
$value = str_contains('This is my name', 'my');
// true

str_finish()
str_finish函数添加字符到字符串结尾：
$string = str_finish('this/string', '/');
// this/string/

str_is()
str_is函数判断给定字符串是否与给定模式匹配，星号可用于表示通配符：
$value = str_is('foo*', 'foobar');
// true
$value = str_is('baz*', 'foobar');
// false

str_plural()
str_plural函数将字符串转化为复数形式，该函数当前只支持英文：
$plural = str_plural('car');
// cars
$plural = str_plural('child');
// children

str_singular()
str_singular函数将字符串转化为单数形式，该函数目前只支持英文：
$singular = str_singular('cars');
// car

str_random()
str_random函数通过指定长度生成随机字符串：
$string = str_random(6);
// xsd213

str_slug()
str_slug函数将给定字符串生成URL友好的格式：
$title = str_slug("Laravel 5 Framework", "-");
// laravel-5-framework

kebab-case()
kebab-case 全小写并且分开的单词组，例如 "hello-world-hi"

str_limit()
限制字符串的字符数量
$value = str_limit('The PHP framework', 7);
// The PHP...

title_case()
函数将给定的字符串转换为「首字母大写」
$converted = title_case('a nice title uses the correct case');
// A Nice Title Uses The Correct Case

transform()
如果给定的值不为空，那么 transform 函数对给定的值执行闭包并返回其结果：
$callback = function ($value) {
return $value * 2;
};
$result = transform(5, $callback);
// 10
默认值或闭包也可以作为方法的第三个参数传递。如果给定值为空白，则返回该值：
$result = transform(null, $callback, 'The value is blank');
// The value is blank

throw_if()
如果给定的布尔表达式计算结果为 true，throw_if 函数抛出给定的异常：
throw_if(!Auth::user()->isAdmin(),AuthorizationException::class);
throw_if(
!Auth::user()->isAdmin(),
AuthorizationException::class,
'You are not allowed to access this page'
);

throw_unless()
如果给定的布尔表达式计算结果为 false，则 throw_unless函数会抛出给定的异常：
throw_unless(Auth::user()->isAdmin(),AuthorizationException::class);
throw_unless(
Auth::user()->isAdmin(),
AuthorizationException::class,
'You are not allowed to access this page'
);

value()
value返回给定值。如果传入一个 Closure 就会返回Closure 执行的结果。
$result = value(true);
// true
$result = value(function () {
return false;
});
// false

with()
with 函数会返回给定的值。如果传入一个 Closure 作为该函数的第二个参数，会返回 Closure 执行的结果：
$callback = function ($value) {
return (is_numeric($value)) ? $value * 2 : 0;
};
$result = with(5, $callback);
// 10
$result = with(null, $callback);
// 0
$result = with(5, null);
// 5

object_get()
获取对象下面的值，支持default获取不到返回。
$relation = 'profile.avatar.filename';
$avatarFilename = object_get($user, $relation);
// `$user->profile->avatar->filename`

optional()
提供对可选对象的访问
optional($user->profile)->address  
profile为空值(null)，这一行代码也不会报错

create_uuid()
返回32位长度的uuid

finish_request_response()
同步返回结果，该方法之后的代码在后端执行，前端接口请求无感知，对于耗时操作例如统计埋点。

dd()
该方法支持同时打印多个变量，然后退出，更方便调试代码。

factory_single_obj()
创建单例
/** @var EsXsUserProfile $obj */
$obj = factory_single_obj(EsXsUserProfile::class);
