<?php 

class Function_System {
    
    static public function writeToFile($fileName, $output) {
        try {
            $f = fopen($fileName, "w");
            fwrite($f, $output);
        } catch (Exception $e) {
            print 'Caught exception: '; print $e->getMessage();
            fclose($f);
            print "\n";
            exit(1);
        }
        fclose($f);
    }

}