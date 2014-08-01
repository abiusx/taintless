# Needed imports
from lib.core.enums import PRIORITY
import subprocess
# Define which is the order of application of tamper scripts against
# the payload
__priority__ = PRIORITY.NORMAL

def tamper(payload):
    '''
    Taintless PTI payload modification
    '''

    retVal = payload
    r=subprocess.check_output(["./taintless", "--construct", "-i" ,  "fragments.txt", "-s",payload,"-q"]);
    if (r!=""):
    	return r;
    else:
    	return payload;



