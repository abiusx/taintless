A file named fragments.txt should be in the working directory when invoking sqlmap with tamper script of Taintless:

./taintless --extract -p path-to-my-app -o whatever-fragments-i-have.txt

ln -s whatever-fragments-i-have.txt fragments.txt

sqlmap -u url-here --tamper sqlmap/tamper.py


