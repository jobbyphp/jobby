# UPGRADE FROM 2.0 to 2.1

As [SuperClosure](https://github.com/jeremeamia/super_closure) was updated from 1.x to 2.x 
(see [ChangeLog-2.1](https://github.com/hellogerard/jobby/blob/master/CHANGELOG-2.1.md)), some of your
[Closures](http://php.net/manual/de/class.closure.php) might not be useable within jobby any more.

The change is the way, SuperClosure handles scoped Closures now.

    class SomeClass
    {
        function someFunction()
        {
            // $fn is a "scoped" Closure. The scope is "SomeClass".
            $fn = function (...) {...};
        }
    }
    
In SuperClosure 2.x, scoped Closures are bound to the class the Closure is defined in.

In the example shown above, *$fn* has the scope "SomeClass". When *$fn* is unserialized, **the scope has
to be available**. In this example, "SomeClass" has to be autoloadable (or on the include_path, 
required/included before unserialization, ...), otherwise unserialization fails.
In most cases, your scope should be available when unserializing, so there is no problem. 

If your scope is not available, you have the chance to declare your Closure *static*, to have your 
Closure unserializable:

    $fn = static function (...) {...};
    
If your scope is not available when unserializing, and your Closure depends on the scope, you will
 have to refactor your code to fit the named requirements.