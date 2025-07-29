echo "yrkvxg/pppppp/6/6/PPPPPP/GXVKRY" > pos.txt

while true ; do
    echo "*** SENTE ***"
    res=$(shogi_depth=3 php makemove.php <pos.txt)
    code=$?
    echo "$res"
    echo "$res" |tail -n1 > pos.txt
    #read v
    if [[ "$code" -eq "7" ]] ; then
        echo "Sente (black) wins!"
        break
    fi
    echo "*** GOTE ***"
    res=$(shogi_depth=5 php makemove.php <pos.txt)
    code=$?
    echo "$res"
    echo "$res" |tail -n1 > pos.txt
    #read v
    if [[ "$code" -eq "7" ]] ; then
        echo "Gote (white) wins!"
        break
    fi
    sleep 1
done
