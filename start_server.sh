#!/bin/bash
#	
#	This file is part of WX4AKQ Offline Tools
#	
#	Copyright (c) 2015-16, Steve Crow, Reid Barden
#	Licensed under the BSD 2-clause “Simplified” License
#	
#	For license information, see the LICENSE.md file or visit
#	http://wx4akq.org/software
#	

CURRDIR=`pwd`
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
cd $DIR

vagrant up
echo Navigate to http://localhost:8080 to access the WX4AKQ Offline Tools.

cd $CURRDIR